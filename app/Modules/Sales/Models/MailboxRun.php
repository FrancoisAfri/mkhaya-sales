<?php

namespace App\Modules\Sales\Models;

use App\Modules\Tenancy\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class MailboxRun extends Model
{
    use BelongsToCompany;

    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['received_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function batch() { return $this->belongsTo(ImportBatch::class, 'import_batch_id'); }
}
