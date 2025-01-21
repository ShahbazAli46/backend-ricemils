<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    
    protected function checkBankBalance($bank_id, $given_amt)
    {
        $bank = Bank::find($bank_id);
        if ($bank && $bank->balance >= $given_amt) {
            return true;
        }
        return false;
    }
}
