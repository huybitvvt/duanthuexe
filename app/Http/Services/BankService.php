<?php


namespace App\Http\Services;


use App\Models\Bank;
use App\Repositories\BankRepository;
use Illuminate\Http\Request;

class BankService
{
    protected $bankRepository;

    public function __construct(BankRepository $bankRepository)
    {
        $this->bankRepository = $bankRepository;
    }
    public function all()
    {
        return $this->bankRepository->all();
    }
    public function index(Request $request)
    {
        return $this->bankRepository->index($request);
    }

    public function store(Request $request)
    {
        return $this->bankRepository->store($request);
    }

    public function update(Request $request, Bank $bank)
    {
        return $this->bankRepository->edit($request, $bank);
    }

    public function checkExistBank($account_number)
    {
        return $this->bankRepository->where('account_number', $account_number)
            ->first();
    }

    // public function searchBankByIdCard(Request $request)
    // {
    //     return $this->bankRepository->searchBankByIdCard($request);
    // }
}
