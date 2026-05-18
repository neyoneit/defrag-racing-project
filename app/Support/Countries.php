<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class Countries
{
    /**
     * ISO-2 codes for which we ship a flag PNG in /public/images/flags.
     * Source of truth — keep this list aligned with the flag assets so
     * the dropdown can't offer a code whose flag image would 404.
     */
    public const CODES = [
        'AD','AE','AF','AG','AL','AM','AR','AT','AU','AZ',
        'BA','BB','BD','BE','BF','BG','BH','BI','BJ','BN',
        'BO','BR','BS','BT','BW','BY','BZ','CA','CD','CF',
        'CG','CH','CI','CL','CM','CN','CO','CR','CU','CV',
        'CY','CZ','DE','DJ','DK','DM','DO','DZ','EC','EE',
        'EG','ER','ES','ET','EU','FI','FJ','FM','FR','GA',
        'GB','GD','GE','GH','GM','GN','GQ','GR','GT','GW',
        'GY','HK','HN','HR','HT','HU','ID','IE','IL','IN',
        'IQ','IR','IS','IT','JM','JO','JP','KE','KG','KH',
        'KI','KM','KN','KP','KR','KW','KY','KZ','LA','LB',
        'LC','LI','LK','LR','LS','LT','LU','LV','LY','MA',
        'MC','MD','ME','MG','MH','MK','ML','MM','MN','MR',
        'MT','MU','MV','MW','MX','MY','MZ','NA','NE','NG',
        'NI','NL','NO','NP','NR','NZ','OM','PA','PE','PG',
        'PH','PK','PL','PR','PS','PT','PW','PY','QA','RO',
        'RS','RU','RW','SA','SB','SC','SD','SE','SG','SI',
        'SK','SL','SM','SN','SO','SR','SS','ST','SV','SY',
        'SZ','TD','TG','TH','TJ','TL','TM','TN','TO','TR',
        'TT','TV','TW','TZ','UA','UG','US','UY','UZ','VA',
        'VC','VE','VN','VU','WS','XK','YE','ZA','ZM','ZW',
    ];

    /**
     * Code => display-name map for use as Filament / select options.
     * Names come from PHP intl (locale_get_display_region); cached per
     * request because the lookup is cheap but the call site (form
     * render) hits it 200x.
     */
    public static function options(): array
    {
        return Cache::store('array')->rememberForever('countries.options', function () {
            $out = [];
            foreach (self::CODES as $code) {
                $name = function_exists('locale_get_display_region')
                    ? locale_get_display_region('-' . $code, 'en')
                    : $code;
                $out[$code] = $name && $name !== $code ? "{$code} — {$name}" : $code;
            }
            asort($out, SORT_NATURAL);
            return $out;
        });
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && in_array(strtoupper($code), self::CODES, true);
    }
}
