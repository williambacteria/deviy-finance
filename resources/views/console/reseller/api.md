# 德威云代理 API · V1

接口地址：`https://www.deviy.cn`。仅使用 HTTPS。账户币种为人民币 CNY，金额字符串单位为元。文档版本：2026-11-17。

## 1. 在魔方财务中添加接口

1. 在本站“控制台 → 代理与 API”提交平台名称、网址和用途，等待审核。
2. 审核通过后，输入本站登录密码，生成独立 API 密钥并立即保存。
3. 在下游魔方财务后台添加“魔方财务 API”，选择 `zjmf_api`：接口地址填写 `https://www.deviy.cn`，用户名填写本站注册邮箱，密码/API 密钥填写生成的 `dvyk_` 密钥。
4. 拉取商品，按需导入商品及配置项，再设置下游自己的销售价格。不要把本站登录密码填入 API 密钥栏。
5. 在本站账户充值。客户下单时，从代理本站账户余额结算，按本站实际配置与周期计价。审核开通 API 不产生额外折扣。

此协议兼容旧版魔方财务 `zjmf_api` 的常用资源接口，不是魔方 v10 原生接口。第三方模板或专用模块可能需要适配。接口不暴露迁移专用隐藏商品。尚未开通、暂停或删除的产品会返回其真实状态，不返回虚假的运行信息。

## 2. 认证与限流

### 获取 Token

`POST /zjmf_api_login`，支持表单或 JSON：

| 参数 | 必填 | 说明 |
|---|---|---|
| username | 是 | 本站账户邮箱 |
| password | 是 | 独立 API 密钥 `dvyk_...` |

```bash
curl -X POST https://www.deviy.cn/zjmf_api_login \
  -H 'Accept: application/json' \
  --data-urlencode 'username=your-email@example.com' \
  --data-urlencode 'password=YOUR_API_KEY'
```

```json
{"status":200,"msg":"登录成功","jwt":"dvy_TOKEN","expires_in":7200,"data":{"jwt":"dvy_TOKEN","expires_in":7200},"request_id":"追踪编号"}
```

`jwt` 字段为不透明 Bearer Token，有效期2小时，不要自行解析。后续请求添加 `Authorization: Bearer dvy_TOKEN`。支持使用 HTTP Basic Auth（账号为邮箱，密码为 API 密钥）调用资源接口，供魔方选品适配器使用。

每个代理每分钟最多120次资源请求，密钥登录按账户与来源IP限制每分钟10次；同一IP另受全站限流约束。收到429后延迟重试。每次最多保留5个有效 Token，超过时淘汰最旧 Token。重置密钥或停用代理后，旧凭据失效。IP白名单同时限制登录和资源请求。

## 3. 公共响应

成功示例：`{"status":200,"msg":"操作成功","data":{},"request_id":"..."}`。HTTP状态码与业务 `status` 对应。响应禁止缓存。

| 状态码 | 处理方式 |
|---|---|
| 401 | 密钥错误、Token失效或未认证；重新登录 |
| 403 | 未审核、已停用、实名/账户状态异常或IP不允许 |
| 404 | 商品/账单/实例不存在，或不属于当前账户 |
| 405 | HTTP方法错误 |
| 409 | 相同幂等键被用于不同订单内容 |
| 422 | 参数、余额、库存、周期或产品能力不满足要求，阅读msg |
| 429 | 限流或本账户正在处理另一笔请求，稍后重试 |
| 503 | 结果暂未完成；先查询订单或实例，保留原购物车位置重试结算 |

查询问题时请提供 `request_id`；不要在工单中发送 API 密钥或 Token。

## 4. 商品同步

### 商品简表

`GET /api/product/proinfo`

返回 `data.info`，同时提供 `data.list` 别名。条目含 `id/name/gid/group_name/type/description/location_version/stock_control/qty/prices/region`，`data.currency` 为币种列表。`location_version` 变化后重新同步商品详情。库存 `qty=0` 时不可购买。

### 商品完整配置

`GET /api/product/prodetail`，可传 `id` 或 `ids[]` 过滤；不传返回全部在售商品。返回 `data.detail`，以商品ID为键，包含商品信息、`product_pricings`、`config_groups`、`customfields` 及 `config_links`。

`GET /cart/get_product_config?pid=商品ID` 返回单商品配置：

```json
{"status":200,"data":{"products":{"id":123,"name":"示例商品"},"product_pricings":[{"currency":1,"code":"CNY","monthly":"60.00"}],"config_groups":[{"id":123,"options":[{"id":1012301,"option_name":"配置项","option_type":1,"sub":[{"id":1012301001,"option_name":"示例配置","pricings":[{"monthly":"0.00"}]}]}]}]}}
```

配置项ID和子选项ID均以实际返回值为准，不要硬编码。选择型配置提交子选项ID；数量型额外配置（`option_type=14`）提交增加份数，基础数量和每份数量在选项说明中，0代表基础配置。可售周期为 `monthly/quarterly/semiannually/annually`；不存在或价格为 `-1.00` 的周期不可购买。

## 5. 下单与结算

### 加入购物车

`POST /cart/add_to_shop`

| 参数 | 必填 | 说明 |
|---|---|---|
| pid | 是 | 本站商品ID |
| billingcycle | 是 | 可售计费周期 |
| qty | 否 | 数量，默认1，最大10 |
| configoption[配置项ID] | 否 | 选择项的子选项ID，或数量型的增加份数 |
| request_id | 推荐 | 代理侧唯一订单号，用于防止重复加购 |

也可通过 `Idempotency-Key` 请求头传递唯一订单号（优先于 `request_id`）。相同键及配置返回相同购物车位置；相同键、不同配置返回409。不传键则每次创建新位置，重试加购可能导致重复订单，请记录返回的 `data.i`。

### 余额结算

`POST /cart/settle`：`payment=Credit`，`pos[]=上一步的data.i`。每次结算一个购物车位置，该位置可包含最多10台同配置产品。

```bash
curl -X POST https://www.deviy.cn/cart/settle \
  -H "Authorization: Bearer $DEVIY_API_TOKEN" \
  --data-urlencode 'payment=Credit' --data-urlencode 'pos[]=123'
```

返回 `data.orderid`、`data.invoiceid`、`data.hostid`（本站实例ID数组）、`data.amount` 和 `data.paid`。同一位置重复结算返回同一已付订单，不重复扣款。余额不足会保留待付订单，充值后使用相同 `pos[]` 继续结算。购物车未生成订单时24小时过期。

商品金额按服务端重新校验的配置与周期计算，不接受客户端传入价格。`paid=true` 表示本站付款成功，实例开通可能仍在队列中，需轮询 `/host/header`，不可因为仍在开通而重新下单。接口不支持优惠券叠加或由下游修改本站价格。登录名及初始密码由产品开通流程生成，开通后从实例详情读取。

### 支付已有账单

`POST /apply_credit`，参数 `invoiceid`（或 `id`）。仅支付当前账户名下的有效待付账单；已支付账单返回原结果，不重复扣款。历史不可支付账单沿用本站限制。

## 6. 账户与实例查询

`GET /host/list`：`page`默认1、`limit`默认20最大100、`search`可按实例名/ID筛选。返回 `data.list/total/page/limit/currency` 及账户余额 `data.credit`。

`GET /host/header?host_id=实例ID`（别名 `/host/dedicatedserver`），返回 `data.host_data`：本站实例ID、商品、状态、到期时间Unix秒、续费周期与金额、连接IP、端口、账号及密码。仅向实例所属代理返回凭据。CDN不返回远程连接及操作系统信息。

`GET /api/host/sync?host_id=实例ID`：返回 `data.host_data`。

`GET /host/trafficusage?host_id=实例ID`：返回已同步的流量字段；尚无数据时 `available=false`，不伪造使用量。

状态含 `Pending`（待开通/开通中）、`Active`、`Suspended`、`Deleted`，具体进度见 `provision_status`。`Active` 是产品服务状态，电源状态另通过管理接口查询。迁入产品续费保留原合同价格。

## 7. 续费与到期不续费

`POST /host/renew`：`hostid`为本站实例ID，`billingcycles`为可续费周期。返回待付 `invoiceid`，再调用 `/apply_credit` 完成余额付款。相同实例和周期有待付续费订单时复用该订单。原请求异常时先核对账单，不要反复创建新续费意图。

`POST /host/cancel`：`id`为本站实例ID，`type=Endofbilling`，设置到期不自动续费，不删除当前产品、不退余额。立即终止、退款、资源降配请提交工单处理。

## 8. 常用实例管理

`POST /provision/default`：`id=本站实例ID`，`func`为操作类型。

| func | 参数 | 说明 |
|---|---|---|
| status | 无 | 获取已同步的电源状态 |
| on | 无 | 开机 |
| off | 无 | 关机 |
| reboot | 无 | 重启 |
| reinstall | os | 重装系统，必须使用该产品允许的系统值 |
| crack_pass | password；裸金属另需force_shutdown=1 | 重置实例密码，至少8位 |

裸金属同时提供 `/dcim/on`、`/dcim/off`、`/dcim/reboot`、`/dcim/reinstall`、`/dcim/crack_pass` 别名，参数同上。仅在产品支持且实例状态允许时可执行；暂停、终止、核查中的实例不能通过电源接口恢复业务。

按实例详情返回的 `system_button` 与 `cloud_os` 展示可用操作。CDN通过产品专属面板管理，不展示电源、重装、远程连接。面板类产品不提供服务器登录密码重置；部分专用模块的VNC、救援、白名单、升降级和流量包购买不在本通用协议范围，使用本站控制台或产品面板。不要展示接口未声明的功能。

## 9. 对接检查顺序

先验证登录、商品同步和配置价格，再验证余额、实例与状态读取。采购联调需要实际扣减代理余额，请使用明确的测试商品与预算。重置密钥、停用代理及白名单变化后应立即验证旧请求已拒绝。代理业务数据与本站账户保持一致，不向下游提供管理员权限。
