<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Handing over removed studies
    |--------------------------------------------------------------------------
    |
    | When the owner deletes a study that physicians can open, or a patient's
    | study expires, those physicians have this many days to keep it as their
    | own before it is deleted.
    |
    */

    'handover_days' => (int) env('STUDY_HANDOVER_DAYS', 7),

];
