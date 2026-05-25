<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reconciliation extends Model
{
    protected $fillable = [
        'collection_id', 'finance_officer_id', 'status', 
        'confirmed_amount', 'bank_slip_number', 'bank_deposit_date', 'notes'
    ];

    public function collection()
    {
        return $this->belongsTo(Collection::class);
    }

    public function financeOfficer()
    {
        return $this->belongsTo(User::class, 'finance_officer_id');
    }

    /**
     * Logic to check if the 3-way match is satisfied
     */
    public function verifyMatch()
    {
        $collection = $this->collection;
        
        // Match 1: Agent Record vs Finance Record
        $agentAmount = $collection->amount;
        $financeAmount = $this->confirmed_amount;
        
        // Match 2: Bank Slip/Deposit presence (simplified for now)
        $hasBankSlip = !empty($this->bank_slip_number);
        
        if ($agentAmount == $financeAmount && $hasBankSlip) {
            $this->status = 'verified';
        } else {
            $this->status = 'suspicious';
        }
        
        $this->save();
        return $this->status;
    }
}
