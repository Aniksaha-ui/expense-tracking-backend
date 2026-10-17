<?php
namespace App\Http\Controllers\Api; use App\Http\Controllers\Controller; use App\Models\FinancialAnomaly;
class FinancialAnomalyController extends Controller { public function index(){return $this->successResponse(FinancialAnomaly::where('user_id',auth()->id())->latest('detected_at')->paginate(20));} public function review(int $id){$a=FinancialAnomaly::where('user_id',auth()->id())->findOrFail($id);$a->update(['reviewed_at'=>now()]);return $this->successResponse($a,'Anomaly marked reviewed.');} }
