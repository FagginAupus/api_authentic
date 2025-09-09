<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'autentique_id',
        'name',
        'status',
        'is_sandbox',
        'document_data',
        'signers',
        'autentique_response',
        'total_signers',
        'signed_count',
        'rejected_count',
        'autentique_created_at',
        'last_checked_at'
    ];

    protected $casts = [
        'document_data' => 'array',
        'signers' => 'array',
        'autentique_response' => 'array',
        'is_sandbox' => 'boolean',
        'autentique_created_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SIGNED = 'signed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PARTIAL = 'partial';

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSigned($query)
    {
        return $query->where('status', self::STATUS_SIGNED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeSandbox($query)
    {
        return $query->where('is_sandbox', true);
    }

    public function scopeProduction($query)
    {
        return $query->where('is_sandbox', false);
    }

    // Accessors
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_SIGNED => 'Assinado',
            self::STATUS_REJECTED => 'Recusado',
            self::STATUS_PARTIAL => 'Parcialmente Assinado',
            default => 'Desconhecido'
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_SIGNED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_PARTIAL => 'info',
            default => 'secondary'
        };
    }

    public function getSigningProgressAttribute()
    {
        if ($this->total_signers == 0) return 0;
        return round(($this->signed_count / $this->total_signers) * 100, 1);
    }

    // Methods
    public function updateStatus()
    {
        if ($this->signed_count == $this->total_signers && $this->total_signers > 0) {
            $this->status = self::STATUS_SIGNED;
        } elseif ($this->rejected_count > 0) {
            $this->status = self::STATUS_REJECTED;
        } elseif ($this->signed_count > 0) {
            $this->status = self::STATUS_PARTIAL;
        } else {
            $this->status = self::STATUS_PENDING;
        }
        
        $this->save();
    }

    public function getSignerEmails()
    {
        $emails = [];
        foreach ($this->signers as $signer) {
            if (!empty($signer['email'])) {
                $emails[] = $signer['email'];
            }
        }
        return $emails;
    }

    public function getSignerPhones()
    {
        $phones = [];
        foreach ($this->signers as $signer) {
            if (!empty($signer['phone'])) {
                $phones[] = $signer['phone'];
            }
        }
        return $phones;
    }
}