<?php

namespace App\Services\Kitchen;

use RuntimeException;

/** Aksi karyawan yang tidak sah untuk keadaan order saat ini (mis. order sudah selesai). Pesannya aman ditampilkan. */
class WorkflowException extends RuntimeException {}
