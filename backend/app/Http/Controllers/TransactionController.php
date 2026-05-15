<?php

namespace App\Http\Controllers;

use App\Services\Transaction\TransactionService;
use App\Services\Transaction\TransactionValidationService;
use App\Services\Transaction\TransactionConverterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    private TransactionService $transactionService;
    private TransactionValidationService $validationService;
    private TransactionConverterService $converterService;

    public function __construct()
    {
        $this->transactionService = new TransactionService();
        $this->validationService = new TransactionValidationService();
        $this->converterService = new TransactionConverterService();
    }

    protected function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) abort(401, 'Unauthorized');
        return $userId;
    }

    public function index(Request $request)
    {
        try {
            $filters = $this->validationService->validateIndex($request->all(), $this->getUserId());
            $fetchAll = $request->boolean('fetch_all');
            $includeAnomalies = $request->boolean('include_anomalies', false);
            $limit = $filters['limit'] ?? 50;

            $result = $this->transactionService->getTransactions(
                $this->getUserId(),
                $filters,
                $includeAnomalies,
                $limit,
                $fetchAll
            );

            return response()->json(['status' => 'success', ...$result]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validationService->validateStore($request->all(), $this->getUserId());

            // Проверка курса валюты
            if (!$this->converterService->validateExchangeRate($validated['currency_id'], $validated['date'])) {
                $currency = \App\Models\Currency::find($validated['currency_id']);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => ['date' => ["На дату {$validated['date']} нет курса для валюты {$currency->code}."]]
                ], 422);
            }

            $transaction = $this->transactionService->createTransaction($this->getUserId(), $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction created successfully',
                'data' => $transaction
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => ['category_ids' => [$e->getMessage()]]
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $transaction = $this->transactionService->getTransaction($this->getUserId(), $id);

            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $transaction = $this->transactionService->getTransaction($this->getUserId(), $id);

            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ], 404);
            }

            $validated = $this->validationService->validateUpdate($request->all());
            $transaction = $this->transactionService->updateTransaction($transaction, $validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction updated successfully',
                'data' => $transaction
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $transaction = $this->transactionService->getTransaction($this->getUserId(), $id);

            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ], 404);
            }

            $this->transactionService->deleteTransaction($transaction);

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    public function summary(Request $request)
    {
        try {
            $validated = $this->validationService->validateSummary($request->all());
            $month = $validated['month'] ?? date('m');
            $year = $validated['year'] ?? date('Y');
            $excludeAnomalies = $request->boolean('exclude_anomalies', true);

            $summary = $this->transactionService->getMonthlySummary(
                $this->getUserId(),
                (int)$month,
                (int)$year,
                $excludeAnomalies
            );

            return response()->json([
                'status' => 'success',
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transaction summary'
            ], 500);
        }
    }

    public function recent(Request $request)
    {
        try {
            $validated = $this->validationService->validateRecent($request->all());
            $limit = $validated['limit'] ?? 10;
            $includeAnomalies = $request->boolean('include_anomalies', false);

            $result = $this->transactionService->getRecentTransactions(
                $this->getUserId(),
                $limit,
                $includeAnomalies
            );

            return response()->json([
                'status' => 'success',
                'data' => $result['data'],
                'meta' => $result['meta']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recent transactions'
            ], 500);
        }
    }

    public function massDelete(Request $request)
    {
        try {
            $validated = $this->validationService->validateMassDelete($request->all());
            $deletedCount = $this->transactionService->massDeleteTransactions(
                $this->getUserId(),
                $validated['transaction_ids']
            );

            return response()->json([
                'status' => 'success',
                'message' => "Deleted {$deletedCount} transactions",
                'data' => ['deleted_count' => $deletedCount]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function markAsAnomaly($id, Request $request)
    {
        try {
            $validated = $this->validationService->validateMarkAnomaly($request->all());

            $transaction = $this->transactionService->getTransaction($this->getUserId(), $id);

            if (!$transaction) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Transaction not found'
                ], 404);
            }

            $transaction = $this->transactionService->markAsAnomaly(
                $transaction,
                $validated['is_anomaly'],
                $validated['reason'] ?? null
            );

            $statusText = $validated['is_anomaly'] ? 'отмечена как аномальная/разовая' : 'отмечена как обычная';

            return response()->json([
                'status' => 'success',
                'message' => "Транзакция {$statusText}",
                'data' => $transaction
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark transaction: ' . $e->getMessage()
            ], 404);
        }
    }

    public function getAnomalies(Request $request)
    {
        try {
            $validated = $this->validationService->validateGetAnomalies($request->all());

            $result = $this->transactionService->getAnomalies(
                $this->getUserId(),
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            return response()->json([
                'status' => 'success',
                'data' => $result['data'],
                'meta' => $result['meta']
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch anomalies: ' . $e->getMessage()
            ], 500);
        }
    }

    public function suggestCategory(Request $request)
    {
        try {
            $validated = $request->validate([
                'description' => 'required|string|min:3',
                'type' => 'nullable|in:income,expense'
            ]);

            $categorizer = new \App\Services\TransactionCategorizer($this->getUserId());
            $suggestion = $categorizer->suggest(
                $validated['description'],
                $validated['type'] ?? null
            );

            if ($suggestion) {
                $category = \App\Models\Category::find($suggestion['category_id']);
                if ($category && $category->user_id == $this->getUserId()) {
                    return response()->json([
                        'status' => 'success',
                        'data' => [
                            'category_id' => $category->id,
                            'category_name' => $category->name,
                            'category_type' => $category->type,
                            'confidence' => $suggestion['confidence']
                        ]
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to suggest category: ' . $e->getMessage()
            ], 500);
        }
    }
}