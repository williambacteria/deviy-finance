<?php
namespace App\Support;
use Illuminate\Support\Carbon;
final class UpstreamExpiry
{
    public static function from(array $detail): ?Carbon
    {
        $value=data_get($detail,'data.host_data.nextduedate')??data_get($detail,'host_data.nextduedate')??data_get($detail,'data.nextduedate')??data_get($detail,'nextduedate')??data_get($detail,'data.expires_at')??data_get($detail,'expires_at')??data_get($detail,'expire_at');
        if(!$value){return null;}
        try {return is_numeric($value) ? Carbon::createFromTimestamp((int)$value,config('app.timezone')) : Carbon::parse($value,config('app.timezone'));}catch(\Throwable){return null;}
    }
}
