<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'adresse',
        'ville',
        'code_postal',
        'pays',
        'statut',
        'synced_at'
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Scope pour les clients actifs
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    // Scope pour les clients non synchronisés
    public function scopeNonSynchronise($query)
    {
        return $query->whereNull('synced_at')
                     ->orWhere('synced_at', '<', 'updated_at');
    }

    // Marquer comme synchronisé
    public function marquerSynchronise()
    {
        $this->synced_at = now();
        $this->save();
    }
}
