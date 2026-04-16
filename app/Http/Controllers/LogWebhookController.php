<?php

namespace App\Http\Controllers;

use App\Events\LogReceived;
use App\Models\Project;
use App\Models\ProjectLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LogWebhookController extends Controller
{
    /**
     * POST /api/logs/{project}
     *
     * Protected by a simple API token in the Authorization or X-Api-Token header.
     * The token must match LOG_WEBHOOK_TOKEN in .env.
     *
     * Body (JSON):
     *   level       string  error|warning|info|debug   (default: info)
     *   message     string  required
     *   channel     string  optional
     *   stack_trace string  optional
     *   context     object  optional  { user_id, url, ... }
     *   source_app  string  optional
     *   environment string  optional
     *
     * Example curl:
     *   curl -X POST https://your-app/api/logs/5 \
     *     -H "Authorization: Bearer YOUR_TOKEN" \
     *     -H "Content-Type: application/json" \
     *     -d '{"level":"error","message":"Null pointer","source_app":"go-api"}'
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        // ── Project lookup ───────────────────────────────────────────────────
        $project = Project::find($projectId);
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        // ── Per-project token auth ───────────────────────────────────────────
        // Accept "Authorization: Bearer <token>" or "X-Api-Token: <token>"
        $provided = $request->bearerToken() ?? $request->header('X-Api-Token');

        if (!$project->webhook_token || !$provided ||
            !hash_equals((string) $project->webhook_token, (string) $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── Validation ───────────────────────────────────────────────────────
        $validator = Validator::make($request->all(), [
            'level'       => 'sometimes|string|in:error,warning,info,debug',
            'message'     => 'required|string|max:5000',
            'channel'     => 'sometimes|nullable|string|max:80',
            'stack_trace' => 'sometimes|nullable|string',
            'context'     => 'sometimes|nullable|array',
            'source_app'  => 'sometimes|nullable|string|max:120',
            'environment' => 'sometimes|nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Validation failed', 'details' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // ── Persist ──────────────────────────────────────────────────────────
        $log = ProjectLog::create([
            'project_id'  => $project->id,
            'level'       => $data['level'] ?? 'info',
            'message'     => $data['message'],
            'channel'     => $data['channel'] ?? null,
            'stack_trace' => $data['stack_trace'] ?? null,
            'context'     => $data['context'] ?? null,
            'source_app'  => $data['source_app'] ?? null,
            'environment' => $data['environment'] ?? null,
        ]);

        // ── Broadcast (real-time) ─────────────────────────────────────────────
        broadcast(new LogReceived($project->id, [
            'id'          => $log->id,
            'project_id'  => $log->project_id,
            'level'       => $log->level,
            'message'     => $log->message,
            'channel'     => $log->channel,
            'source_app'  => $log->source_app,
            'environment' => $log->environment,
            'created_at'  => $log->created_at->toISOString(),
        ]));

        return response()->json(['ok' => true, 'id' => $log->id], 201);
    }
}
