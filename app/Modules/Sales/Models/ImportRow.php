<?php

namespace App\Modules\Sales\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** One staged source row — not audited per row; the batch is. */
class ImportRow extends Model
{
    use BelongsToCompany;

    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['data' => 'array', 'messages' => 'array'];
}
