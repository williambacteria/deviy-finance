document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('textarea[data-autosize]').forEach((textarea) => {
        const resize = () => {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        };
        resize();
        textarea.addEventListener('input', resize);
    });

    const mobileToggle = document.querySelector('[data-mobile-toggle]');
    const mobileNav = document.getElementById('dv-nav');

    if (mobileToggle) {
        const header = mobileToggle.closest('.dv-header');

        const setMobileMenu = (open) => {
            document.body.classList.toggle('dv-mobile-open', open);
            mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            mobileToggle.setAttribute('aria-label', open ? '关闭菜单' : '打开菜单');
        };

        mobileToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            setMobileMenu(!document.body.classList.contains('dv-mobile-open'));
        });

        mobileNav?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => setMobileMenu(false));
        });

        document.addEventListener('click', (event) => {
            if (!document.body.classList.contains('dv-mobile-open')) {
                return;
            }
            if (header && !header.contains(event.target)) {
                setMobileMenu(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setMobileMenu(false);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 980) {
                setMobileMenu(false);
            }
        });
    }

    const consoleNavToggle = document.querySelector('[data-console-nav-toggle]');
    const consoleSidebar = document.getElementById('console-sidebar');

    if (consoleNavToggle && consoleSidebar) {
        const setConsoleNav = (open) => {
            document.body.classList.toggle('console-mobile-nav-open', open);
            consoleNavToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            consoleNavToggle.setAttribute('aria-label', open ? '关闭控制台菜单' : '打开控制台菜单');
        };

        consoleNavToggle.addEventListener('click', (event) => {
            event.stopPropagation();
            setConsoleNav(!document.body.classList.contains('console-mobile-nav-open'));
        });

        document.querySelectorAll('[data-console-nav-close]').forEach((button) => {
            button.addEventListener('click', () => setConsoleNav(false));
        });
        consoleSidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => setConsoleNav(false));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setConsoleNav(false);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 820) {
                setConsoleNav(false);
            }
        });
    }

    document.querySelectorAll('[data-dismiss]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.flash')?.remove());
    });

    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-auto-submit]').forEach((field) => {
        field.addEventListener('change', () => field.form?.submit());
    });

    document.querySelectorAll('[data-alert]').forEach((button) => {
        button.addEventListener('click', () => window.alert(button.dataset.alert));
    });

    document.querySelectorAll('[data-print]').forEach((button) => {
        button.addEventListener('click', () => window.print());
    });

    document.querySelectorAll('[data-vnc-popup]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            const width = Math.min(1280, window.screen.availWidth - 80);
            const height = Math.min(860, window.screen.availHeight - 80);
            const left = Math.max(0, Math.round((window.screen.availWidth - width) / 2));
            const top = Math.max(0, Math.round((window.screen.availHeight - height) / 2));
            window.open(
                link.href,
                'deviy_vnc_' + (link.dataset.vncPopup || 'console'),
                'popup=yes,width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',resizable=yes,scrollbars=yes'
            );
        });
    });

    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const value = button.dataset.copy || '';
            try {
                await navigator.clipboard.writeText(value);
            } catch (error) {
                const input = document.createElement('textarea');
                input.value = value;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }
            const original = button.textContent;
            button.textContent = '已复制';
            window.setTimeout(() => {
                button.textContent = original;
            }, 1200);
        });
    });

    document.querySelectorAll('[data-toggle-secret]').forEach((button) => {
        button.addEventListener('click', () => {
            const item = button.closest('.remote-item');
            const secret = item?.querySelector('[data-secret-value]');
            if (!secret) {
                return;
            }
            const hidden = secret.dataset.revealed !== '1';
            secret.dataset.revealed = hidden ? '1' : '0';
            secret.textContent = hidden ? secret.dataset.secretValue : '••••••••';
            button.textContent = hidden ? '隐藏' : '显示';
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.closest('.password-field');
            const input = field?.querySelector('input');
            if (!input) {
                return;
            }
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            const showIcon = button.querySelector('[data-password-eye-show]');
            const hideIcon = button.querySelector('[data-password-eye-hide]');
            if (showIcon) {
                showIcon.hidden = show;
            }
            if (hideIcon) {
                hideIcon.hidden = !show;
            }
            button.setAttribute('aria-label', show ? '隐藏密码' : '显示密码');
        });
    });

    (() => {
        const dialog = document.getElementById('password-reset-dialog');
        const trigger = document.querySelector('[data-password-reset-open]');
        const input = dialog?.querySelector('[data-password-reset-input]');
        if (!dialog || !trigger || !input) {
            return;
        }

        const generate = () => {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
            let password = '';
            for (let i = 0; i < 12; i += 1) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            input.value = password;
        };

        trigger.addEventListener('click', () => {
            if (!input.value) generate();
            dialog.showModal();
        });
        dialog.querySelectorAll('[data-password-generate]').forEach((button) => {
            button.addEventListener('click', generate);
        });
        dialog.querySelectorAll('[data-password-reset-close]').forEach((button) => {
            button.addEventListener('click', () => dialog.close());
        });
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });

        if (dialog.hasAttribute('data-open-on-load')) {
            dialog.showModal();
        }
    })();

    const monitorRoot = document.querySelector('[data-monitor-data]');
    if (monitorRoot) {
        let monitors = [];
        try {
            monitors = JSON.parse(monitorRoot.dataset.monitorData || '[]');
        } catch (error) {
            monitors = [];
        }

        const rangeLabels = { '24h': '24 小时', '7d': '7 天', '30d': '30 天' };
        let monitorRange = '24h';

        const sampleSeries = (series, count) => {
            if (series.length === count) {
                return series.slice();
            }

            return Array.from({ length: count }, (_, index) => {
                const source = Math.round(index * (series.length - 1) / Math.max(1, count - 1));

                return Number(series[source] || 0);
            });
        };

        const timeLabel = (range, index, count) => {
            if (range === '24h') {
                return String(index).padStart(2, '0') + ':00';
            }

            const days = range === '7d' ? 7 : 30;
            const date = new Date();
            date.setDate(date.getDate() - (days - 1) + index);

            return (date.getMonth() + 1).toString().padStart(2, '0') + '-' + date.getDate().toString().padStart(2, '0');
        };

        const tooltipTimeLabel = (range, index) => {
            if (range !== '24h') {
                return timeLabel(range, index);
            }

            return String(index).padStart(2, '0') + ':00 - ' + String((index + 1) % 24).padStart(2, '0') + ':00';
        };

        const formatValue = (monitor, value) => {
            const number = monitor.unit === '%' ? Math.round(value) : Number(value).toFixed(1);

            return number + monitor.unit;
        };

        const renderMonitor = () => {
            const count = monitorRange === '24h' ? 24 : (monitorRange === '7d' ? 7 : 30);
            const width = 240;
            const height = 60;
            const padding = 4;

            monitors.forEach((monitor) => {
                const card = monitorRoot.querySelector('[data-monitor-card="' + monitor.key + '"]');
                if (!card) {
                    return;
                }
                if (monitor.no_data) {
                    const value = card.querySelector('[data-monitor-value]');
                    if (value) {
                        value.textContent = '--';
                    }
                    return;
                }

                const values = sampleSeries(monitor.series || [], count);
                const points = values.map((value, index) => {
                    const x = count === 1 ? width / 2 : index * (width / (count - 1));
                    const ratio = Math.max(0, Math.min(1, Number(value) / Math.max(1, Number(monitor.max || 100))));
                    const y = height - padding - ratio * (height - padding * 2);

                    return { x, y, value, label: timeLabel(monitorRange, index, count) };
                });

                const linePoints = points.map((point) => point.x.toFixed(1) + ',' + point.y.toFixed(1)).join(' ');
                const areaPoints = '0,' + height + ' ' + linePoints + ' ' + width + ',' + height;
                const dots = points.map((point, index) => {
                    return '<circle cx="' + point.x.toFixed(1) + '" cy="' + point.y.toFixed(1) + '" r="8" fill="transparent" data-monitor-point="' + index + '"></circle>' +
                        '<circle cx="' + point.x.toFixed(1) + '" cy="' + point.y.toFixed(1) + '" r="2.4" fill="' + monitor.color + '" opacity="0" data-monitor-dot="' + index + '"></circle>';
                }).join('');

                const svg = card.querySelector('[data-monitor-chart]');
                svg.innerHTML = '<polygon points="' + areaPoints + '" fill="' + monitor.fill + '"></polygon>' +
                    '<polyline points="' + linePoints + '" fill="none" stroke="' + monitor.color + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline>' +
                    '<line x1="0" y1="0" x2="0" y2="' + height + '" stroke="' + monitor.color + '" stroke-width="1" stroke-dasharray="3 3" opacity="0" data-monitor-guide></line>' +
                    dots;

                const valueLabel = card.querySelector('[data-monitor-value]');
                if (valueLabel) {
                    valueLabel.textContent = formatValue(monitor, values[values.length - 1] || 0);
                }

                const axis = card.querySelector('[data-monitor-axis]');
                if (axis) {
                    const labels = axis.querySelectorAll('span');
                    if (labels.length === 3) {
                        labels[0].textContent = points[0]?.label || '';
                        labels[1].textContent = points[Math.floor(points.length / 2)]?.label || '';
                        labels[2].textContent = points[points.length - 1]?.label || '';
                    }
                }

                const tooltip = card.querySelector('[data-monitor-tooltip]');
                const guide = svg.querySelector('[data-monitor-guide]');
                svg.querySelectorAll('[data-monitor-point]').forEach((point) => {
                    const index = Number(point.dataset.monitorPoint);
                    const data = points[index];
                    if (!data) {
                        return;
                    }

                    point.addEventListener('mouseenter', () => {
                        svg.querySelectorAll('[data-monitor-dot]').forEach((dot) => dot.setAttribute('opacity', '0'));
                        const dot = svg.querySelector('[data-monitor-dot="' + index + '"]');
                        dot?.setAttribute('opacity', '1');
                        if (guide) {
                            guide.setAttribute('x1', data.x);
                            guide.setAttribute('x2', data.x);
                            guide.setAttribute('opacity', '0.7');
                        }
                        if (tooltip) {
                            tooltip.innerHTML = '<b>' + monitor.label + ' ' + formatValue(monitor, data.value) + '</b><span>' + tooltipTimeLabel(monitorRange, index) + '</span>';
                            tooltip.style.left = (data.x / width * 100) + '%';
                            tooltip.style.top = (data.y / height * 100) + '%';
                            tooltip.classList.add('visible');
                        }
                    });

                    point.addEventListener('mouseleave', () => {
                        const dot = svg.querySelector('[data-monitor-dot="' + index + '"]');
                        dot?.setAttribute('opacity', '0');
                        guide?.setAttribute('opacity', '0');
                        tooltip?.classList.remove('visible');
                    });
                });
            });

            document.querySelectorAll('[data-monitor-range]').forEach((button) => {
                button.classList.toggle('active', button.dataset.monitorRange === monitorRange);
            });
        };

        document.querySelectorAll('[data-monitor-range]').forEach((button) => {
            button.addEventListener('click', () => {
                monitorRange = button.dataset.monitorRange || '24h';
                renderMonitor();
            });
        });

        renderMonitor();
    }

    (() => {
        const root = document.querySelector('[data-upstream-charts]');
        if (!root) {
            return;
        }

        let charts = {};
        try {
            charts = JSON.parse(root.dataset.upstreamCharts || '{}');
        } catch (error) {
            charts = {};
        }

        const colors = {
            cpu: ['#2563eb'],
            memory: ['#2563eb'],
            disk: ['#7c3aed', '#2563eb'],
            bandwidth: ['#7c3aed', '#2563eb'],
        };
        const fills = ['rgba(37,99,235,.13)', 'rgba(124,58,237,.13)'];
        const rangeMs = { '30m': 30 * 60 * 1000, '24h': 24 * 60 * 60 * 1000, '7d': 7 * 24 * 60 * 60 * 1000, '30d': 30 * 24 * 60 * 60 * 1000 };

        const parseTime = (value) => {
            const time = Date.parse(String(value || '').replace(' ', 'T'));
            return Number.isNaN(time) ? null : time;
        };

        const pad = (value) => String(value).padStart(2, '0');
        const formatTime = (time, range) => {
            const date = new Date(time);
            if (range === '7d' || range === '30d' || range === 'custom') {
                return (date.getMonth() + 1) + '-' + pad(date.getDate()) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
            }
            return pad(date.getHours()) + ':' + pad(date.getMinutes());
        };

        const smoothPath = (points) => {
            if (points.length < 2) {
                return '';
            }
            let path = 'M ' + points[0].x.toFixed(1) + ' ' + points[0].y.toFixed(1);
            for (let i = 0; i < points.length - 1; i += 1) {
                const p0 = points[i - 1] || points[i];
                const p1 = points[i];
                const p2 = points[i + 1];
                const p3 = points[i + 2] || p2;
                const cp1x = p1.x + (p2.x - p0.x) / 6;
                const cp1y = p1.y + (p2.y - p0.y) / 6;
                const cp2x = p2.x - (p3.x - p1.x) / 6;
                const cp2y = p2.y - (p3.y - p1.y) / 6;
                path += ' C ' + cp1x.toFixed(1) + ' ' + cp1y.toFixed(1) + ', ' + cp2x.toFixed(1) + ' ' + cp2y.toFixed(1) + ', ' + p2.x.toFixed(1) + ' ' + p2.y.toFixed(1);
            }
            return path;
        };

        const inputValue = (date) => {
            const d = new Date(date);
            return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        };

        document.querySelectorAll('[data-upstream-chart-card]').forEach((card) => {
            const key = card.dataset.upstreamChartCard;
            const chart = charts[key];
            const svg = card.querySelector('[data-upstream-chart]');
            const wrap = svg?.closest('.upstream-chart-wrap');
            const tooltip = wrap?.querySelector('[data-upstream-tooltip]');
            if (!chart || !svg || !Array.isArray(chart.series)) {
                return;
            }

            const allTimes = chart.series.flatMap((series) => (series.points || []).map((point) => parseTime(point.time)).filter(Boolean));
            const latest = allTimes.length ? Math.max(...allTimes) : Date.now();
            const fullMin = allTimes.length ? Math.min(...allTimes) : latest - rangeMs['30m'];
            const fullMax = latest;
            let range = '30m';
            let customRange = null;
            let viewDomain = null;
            let state = null;
            const rangeButtons = card.querySelectorAll('[data-upstream-range]');
            const customBox = card.querySelector('.upstream-custom-range');
            const startInput = card.querySelector('[data-upstream-start]');
            const endInput = card.querySelector('[data-upstream-end]');

            if (startInput && endInput) {
                startInput.value = inputValue(latest - rangeMs['24h']);
                endInput.value = inputValue(latest);
            }

            const filteredSeries = () => {
                let from = latest - (rangeMs[range] || rangeMs['30m']);
                let to = latest;
                if (range === 'custom' && customRange) {
                    from = customRange[0];
                    to = customRange[1];
                } else if (range === '30m' || range === '24h' || range === '7d' || range === '30d') {
                    from = latest - rangeMs[range];
                }
                if (viewDomain) {
                    from = viewDomain[0];
                    to = viewDomain[1];
                }

                return chart.series.map((series, index) => ({
                    name: series.name || '数据',
                    color: (colors[key] || colors.cpu)[index % (colors[key] || colors.cpu).length],
                    fill: fills[index % fills.length],
                    points: (series.points || []).filter((point) => {
                        const time = parseTime(point.time);
                        return time !== null && time >= from && time <= to;
                    }),
                })).filter((series) => series.points.length > 0);
            };

            const render = () => {
                if (tooltip) {
                    tooltip.hidden = true;
                }
                const width = Math.max(560, Math.round(svg.clientWidth || 900));
                const height = Math.max(200, Math.min(320, Math.round(width * 0.3)));
                const series = filteredSeries();
                if (series.length === 0) {
                    svg.innerHTML = '<text x="' + (width / 2) + '" y="' + (height / 2) + '" text-anchor="middle" fill="#94a3b8" font-size="12">当前范围暂无监控数据</text>';
                    return;
                }

                const margin = { top: 12, right: 16, bottom: 30, left: 58 };
                const plotW = width - margin.left - margin.right;
                const plotH = height - margin.top - margin.bottom;
                const times = series.flatMap((item) => item.points.map((point) => parseTime(point.time)).filter(Boolean));
                const minTime = Math.min(...times);
                const maxTime = Math.max(...times);
                const rangeSpan = Math.max(1, maxTime - minTime);
                const maxValueRaw = Math.max(...series.flatMap((item) => item.points.map((point) => Number(point.value) || 0)));
                const yMax = chart.unit === '%' ? 100 : Math.max(1, maxValueRaw * 1.18);
                const yTicks = 4;
                const x = (time) => margin.left + ((time - minTime) / rangeSpan) * plotW;
                const y = (value) => margin.top + plotH - (Math.max(0, Math.min(yMax, value)) / yMax) * plotH;
                const formatValue = (value) => {
                    if (chart.unit === '%') return Math.round(value) + '%';
                    return (value >= 10 ? value.toFixed(1) : value.toFixed(1)) + chart.unit;
                };

                let content = '';
                content += '<defs>';
                series.forEach((item, index) => {
                    content += '<linearGradient id="upfill-' + key + '-' + index + '" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="' + item.color + '" stop-opacity=".22"/><stop offset="100%" stop-color="' + item.color + '" stop-opacity="0"/></linearGradient>';
                });
                content += '</defs>';

                for (let i = 0; i <= yTicks; i += 1) {
                    const value = (yMax / yTicks) * i;
                    const yy = y(value);
                    content += '<line x1="' + margin.left + '" y1="' + yy.toFixed(1) + '" x2="' + (width - margin.right) + '" y2="' + yy.toFixed(1) + '" stroke="#eef2f8" stroke-width="1"/>';
                    content += '<text x="' + (margin.left - 10) + '" y="' + (yy + 3).toFixed(1) + '" text-anchor="end" fill="#94a3b8" font-size="10">' + formatValue(value) + '</text>';
                }

                const labelSeries = series[0].points;
                const labels = 5;
                for (let i = 0; i <= labels; i += 1) {
                    const ratio = i / labels;
                    const time = minTime + rangeSpan * ratio;
                    const xx = margin.left + plotW * ratio;
                    content += '<text x="' + xx.toFixed(1) + '" y="' + (height - 9) + '" text-anchor="' + (i === 0 ? 'start' : (i === labels ? 'end' : 'middle')) + '" fill="#94a3b8" font-size="9">' + formatTime(time, range) + '</text>';
                    content += '<line x1="' + xx.toFixed(1) + '" y1="' + (margin.top + plotH) + '" x2="' + xx.toFixed(1) + '" y2="' + (margin.top + plotH + 4) + '" stroke="#dbe3ee" stroke-width="1"/>';
                }

                series.forEach((item, index) => {
                    const points = item.points.map((point) => ({ x: x(parseTime(point.time)), y: y(Number(point.value) || 0) }));
                    const line = smoothPath(points);
                    if (index === 0 && points.length > 1) {
                        const area = line + ' L ' + points[points.length - 1].x.toFixed(1) + ' ' + (margin.top + plotH) + ' L ' + points[0].x.toFixed(1) + ' ' + (margin.top + plotH) + ' Z';
                        content += '<path d="' + area + '" fill="url(#upfill-' + key + '-0)"/>';
                    }
                    content += '<path d="' + line + '" fill="none" stroke="' + item.color + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
                });

                content += '<line x1="0" y1="' + margin.top + '" x2="0" y2="' + (margin.top + plotH) + '" stroke="#93c5fd" stroke-width="1" stroke-dasharray="4 4" opacity="0" data-upstream-guide/>';

                state = {
                    series,
                    minTime,
                    maxTime,
                    x,
                    y,
                    width,
                    height,
                    margin,
                    plotW,
                    plotH,
                    fullMin,
                    fullMax,
                };
                svg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
                svg.innerHTML = content;
            };

            rangeButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    range = button.dataset.upstreamRange || '30m';
                    viewDomain = null;
                    rangeButtons.forEach((item) => item.classList.toggle('active', item === button));
                    if (customBox) {
                        customBox.hidden = range !== 'custom';
                    }
                    if (range !== 'custom') {
                        render();
                    }
                });
            });

            card.querySelector('[data-upstream-apply]')?.addEventListener('click', () => {
                const start = startInput ? parseTime(startInput.value) : null;
                const end = endInput ? parseTime(endInput.value) : null;
                if (!start || !end || end <= start) {
                    return;
                }
                range = 'custom';
                customRange = [start, end];
                viewDomain = null;
                render();
            });

            const hideHover = () => {
                const guide = svg.querySelector('[data-upstream-guide]');
                if (guide) {
                    guide.setAttribute('opacity', '0');
                }
                if (tooltip) {
                    tooltip.hidden = true;
                }
            };

            const nearestPoint = (points, time) => points.reduce((nearest, point) => {
                const pointTime = parseTime(point.time);
                if (pointTime === null) {
                    return nearest;
                }
                if (!nearest || Math.abs(pointTime - time) < Math.abs(nearest.time - time)) {
                    return { point, time: pointTime };
                }
                return nearest;
            }, null);

            svg.addEventListener('mousemove', (event) => {
                if (!state || dragging) {
                    return;
                }
                const rect = svg.getBoundingClientRect();
                const svgX = ((event.clientX - rect.left) / rect.width) * state.width;
                if (svgX < state.margin.left || svgX > state.width - state.margin.right) {
                    hideHover();
                    return;
                }
                const ratio = (svgX - state.margin.left) / state.plotW;
                const hoverTime = state.minTime + (state.maxTime - state.minTime) * ratio;
                const anchor = nearestPoint(state.series[0].points, hoverTime);
                if (!anchor) {
                    hideHover();
                    return;
                }

                const guide = svg.querySelector('[data-upstream-guide]');
                if (guide) {
                    const guideX = state.x(anchor.time);
                    guide.setAttribute('x1', guideX);
                    guide.setAttribute('x2', guideX);
                    guide.setAttribute('opacity', '0.75');
                }

                if (!tooltip) {
                    return;
                }
                const rows = state.series.map((item) => {
                    const nearest = nearestPoint(item.points, anchor.time);
                    const value = nearest ? nearest.point.value : 0;
                    const formatted = chart.unit === '%' ? Math.round(value) + '%' : Number(value).toFixed(1) + chart.unit;
                    return '<span><i style="background:' + item.color + '"></i>' + item.name + '<strong>' + formatted + '</strong></span>';
                }).join('');
                tooltip.innerHTML = '<b>' + anchor.point.time + '</b>' + rows;
                tooltip.hidden = false;
                const anchorX = state.x(anchor.time);
                const anchorY = state.y(Number(anchor.point.value) || 0);
                const leftPct = (anchorX / state.width) * 100;
                const placeLeft = leftPct > 62;
                tooltip.style.left = (placeLeft ? Math.max(3, leftPct - 2) : Math.min(96, leftPct + 2)) + '%';
                tooltip.style.top = Math.min(62, Math.max(4, (anchorY / state.height) * 100 - 10)) + '%';
                tooltip.style.transform = placeLeft ? 'translateX(-100%)' : 'translateX(0)';
            });
            let dragging = false;
            let dragStartX = 0;
            let dragDomain = null;
            let wasDragged = false;
            svg.addEventListener('mousedown', (event) => {
                if (!state || event.button !== 0) {
                    return;
                }
                dragging = true;
                wasDragged = false;
                dragStartX = event.clientX;
                dragDomain = [state.minTime, state.maxTime];
                svg.style.cursor = 'grabbing';
                hideHover();
                event.preventDefault();
            });
            window.addEventListener('mousemove', (event) => {
                if (!dragging || !state || !dragDomain) {
                    return;
                }
                const rect = svg.getBoundingClientRect();
                const span = dragDomain[1] - dragDomain[0];
                const delta = ((event.clientX - dragStartX) / Math.max(1, rect.width)) * span;
                if (Math.abs(delta) > 1) {
                    wasDragged = true;
                }
                let from = dragDomain[0] - delta;
                let to = from + span;
                if (from < state.fullMin) {
                    from = state.fullMin;
                    to = from + span;
                }
                if (to > state.fullMax) {
                    to = state.fullMax;
                    from = to - span;
                }
                viewDomain = [from, to];
                render();
            });
            window.addEventListener('mouseup', () => {
                dragging = false;
                dragDomain = null;
                svg.style.cursor = 'grab';
            });
            svg.addEventListener('mouseleave', hideHover);
            svg.addEventListener('wheel', (event) => {
                if (!state) {
                    return;
                }
                event.preventDefault();
                const rect = svg.getBoundingClientRect();
                const ratio = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
                const span = Math.max(1, state.maxTime - state.minTime);
                const fullSpan = Math.max(span, state.fullMax - state.fullMin);
                let nextSpan = span * (event.deltaY > 0 ? 1.25 : 0.8);
                nextSpan = Math.max(60 * 1000, Math.min(fullSpan, nextSpan));
                const center = state.minTime + span * ratio;
                let from = center - nextSpan * ratio;
                let to = from + nextSpan;
                if (from < state.fullMin) {
                    from = state.fullMin;
                    to = from + nextSpan;
                }
                if (to > state.fullMax) {
                    to = state.fullMax;
                    from = to - nextSpan;
                }
                viewDomain = [Math.max(state.fullMin, from), Math.min(state.fullMax, to)];
                hideHover();
                render();
            }, { passive: false });

            render();
        });
    })();

    document.querySelectorAll('.flash').forEach((flash) => {
        window.setTimeout(() => flash.remove(), 5200);
    });
});
