<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupDestination extends Model
{
    protected $fillable = [
        'server_id',
        'account_id',
        'name',
        'type',
        'credentials_encrypted',
        'bucket',
        'remote_path',
        'retention_count',
        'status',
        'last_tested_at',
        'last_sync_at',
    ];

    protected $casts = [
        'credentials_encrypted' => 'encrypted:array',
        'last_tested_at'        => 'datetime',
        'last_sync_at'          => 'datetime',
        'retention_count'       => 'integer',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
