<?php

return [
    App\Providers\AppServiceProvider::class,
    
    // Module Service Providers
    App\Modules\Attendance\AttendanceServiceProvider::class,
    App\Modules\Leave\LeaveServiceProvider::class,
    App\Modules\Claims\ClaimsServiceProvider::class,
];
