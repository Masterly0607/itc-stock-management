<?php

use Illuminate\Support\Carbon;

if (! function_exists('app_datetime')) {
  function app_datetime($value, string $fmt = 'Y-m-d H:i')
  {
    if (blank($value)) return null;
    return Carbon::parse($value)->timezone(config('app.timezone'))->format($fmt);
  }
}
