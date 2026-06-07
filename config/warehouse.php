<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maksimal baris fisik aktif untuk cell MSpart
    |--------------------------------------------------------------------------
    |
    | Client menetapkan bahwa baris di atas angka ini tidak dipakai sebagai
    | lokasi operasional. GA dan evaluasi hanya boleh memakai cell sampai baris
    | ini, meskipun data rack lama masih memiliki total_levels lebih besar.
    |
    */
    'max_active_baris' => (int) env('WAREHOUSE_MAX_ACTIVE_BARIS', 5),
];
