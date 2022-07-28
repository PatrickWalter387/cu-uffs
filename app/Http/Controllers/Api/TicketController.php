<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Api\ApiResponse;
use App\Services\TicketService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    private TicketService $service;

    public function __construct(TicketService $service)
    {
        $this->service = $service;
    }

    public function insertTickets(Request $request, $enrollment_id){
        try {
            if (!$request->user()->isThridPartyCashierEmployee()){
                return ApiResponse::forbidden('User is not allowed to do this operation.');
            }

            $data = [
                'third_party_cashier_employee_id' => $request->user()->id,
                "date_time" => now(),
                "amount" => $request->amount
            ];

            $validation = Validator::make($data, $this->insertTicketsRules());

            if ($validation->fails()) {
                return ApiResponse::badRequest($validation->errors()->all());
            }

            $this->service->insertTicket($enrollment_id, $data);

            return ApiResponse::ok(null);
        } catch (Exception $e) {
            return ApiResponse::badRequest($e->getMessage());
        }
    }

    public function insertTicketsForVisitors(Request $request){
        try {
            if (!$request->user()->isThridPartyCashierEmployee()){
                return ApiResponse::forbidden('User is not allowed to do this operation.');
            }

            $data = [
                'third_party_cashier_employee_id' => $request->user()->id,
                "date_time" => now(),
                "amount" => 1
            ];

            $this->service->insertTicketForVisitors($data);

            return ApiResponse::ok(null);
        } catch (Exception $e) {
            return ApiResponse::badRequest($e->getMessage());
        }
    }

    public function insertTicketsRules(){
        return [
            'amount' => ['required','numeric', 'min:0', 'not_in:0']
        ];
    }
}