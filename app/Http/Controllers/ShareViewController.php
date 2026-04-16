<?php

namespace App\Http\Controllers;

use App\Models\ShareLink;
use Illuminate\Http\Request;

class ShareViewController extends Controller
{
    public function show(string $token)
    {
        $share = ShareLink::where('token', $token)->firstOrFail();

        if (!$share->isValid()) {
            abort(410, 'This share link has expired or been revoked.');
        }

        session(['share_token' => $token, 'share_project_id' => $share->project_id]);

        // Redirect to kanban or notes depending on what's enabled
        if ($share->can_kanban) {
            return redirect()->route('share.kanban', $token);
        } elseif ($share->can_notes) {
            return redirect()->route('share.notes', $token);
        }

        abort(403, 'No accessible sections on this share link.');
    }
}
