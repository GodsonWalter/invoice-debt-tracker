<?php

namespace App\Http\Controllers\Dashboard;

use App\Data\DashboardQueryData;
use App\Exceptions\AI\AiServiceException;
use App\Exceptions\AI\InvalidQueryException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiQueryRequest;
use App\Models\AiQuery;
use App\Services\AI\InvoiceQueryExecutor;
use App\Services\AI\NaturalLanguageQueryService;
use App\Services\AI\QueryValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AiQueryController extends Controller
{
    public function __construct(
        private readonly NaturalLanguageQueryService $aiService,
        private readonly QueryValidationService $validationService,
        private readonly InvoiceQueryExecutor $queryExecutor,
    ) {}

    public function index(): View
    {
        $workspace = app('currentWorkspace');

        $recentQueries = AiQuery::where('workspace_id', $workspace->id)
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.ai-query.index', compact('recentQueries'));
    }

    public function search(AiQueryRequest $request): JsonResponse
    {      

        try {
            $query = $request->validated('query');
            $workspace = app('currentWorkspace');

            // Step 1: Parse using AI service
            $parsed = $this->aiService->parse($query);

            // Step 2: Convert to DTO
            $queryData = DashboardQueryData::fromArray($parsed);

            // Step 3: Validate AI response
            $this->validationService->validate($queryData);

            // Step 4: Execute query
            $results = $this->queryExecutor->execute($queryData, $workspace->id);

            // Step 5: Save query history
            AiQuery::create([
                'workspace_id' => $workspace->id,
                'user_id' => $request->user()->id,
                'query' => $query,
                'parsed_response' => $parsed,
            ]);

            

            // Step 6: Return response
            return response()->json([
                'success' => true,
                'query' => $query,
                'parsed' => $parsed,
                'results' => $results,
                'count' => $results->count(),
            ], Response::HTTP_OK);
        } catch (InvalidQueryException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid query: '.$e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (AiServiceException $e) {
            return response()->json([
                'success' => false,
                'error' => 'AI Service error: ',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred.'.$e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
