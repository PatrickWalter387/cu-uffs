<?php

namespace App\Http\Controllers\Api\V0;

use App\Http\Controllers\Controller;
use App\Interfaces\Services\ITicketService;
use App\Models\Api\ApiResponse;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketController extends Controller
{
    private ITicketService $service;

    public function __construct(ITicketService $service)
    {
        $this->service = $service;
    }

    public function insertTickets(Request $request){
        try {
            $data = [
                'third_party_cashier_employee_id' => $request->user()->id,
                "date_time" => now(),
                "amount" => $request->amount
            ];

            $validation = Validator::make($data, $this->insertTicketsRules());

            if ($validation->fails()) {
                return ApiResponse::badRequest($validation->errors()->all());
            }

            $this->service->insertTicket($request->enrollment_id, $data);

            return ApiResponse::ok(null);
        } catch (Exception $e) {
            return ApiResponse::badRequest($e->getMessage());
        }
    }

    public function insertTicketsForVisitors(Request $request){
        try {
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

    public function getTickets(Request $request){
        try {
            $tickets = $this->service->getTicketsByUsername($request->user()->uid);

            return ApiResponse::ok($tickets);
        } catch (Exception $e) {
            return ApiResponse::badRequest($e->getMessage());
        }
    }

    public function getTicketBalance(Request $request){
        try {
            $tickets = $this->service->getTicketBalance($request->user()->uid);

            return ApiResponse::ok($tickets);
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
