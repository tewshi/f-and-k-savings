<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'amount',
    ];

    /**
     * Returns the user that this wallet belongs to
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Returns payments made on this wallet
     * @return HasMany
     */
    public function payments()
    {
        return $this->hasMany(WalletPayment::class);
    }

    public function deposit($amount = 0) {
        $this->amount += $amount;
        $this->save();
    }

}
