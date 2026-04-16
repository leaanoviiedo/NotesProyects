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

        // If the user is authenticated AND is already an owner/member, send to the full app
        if (auth()->check() && $share->project->isMember(auth()->user())) {
            if ($share->can_kanban) {
                return redirect()->route('kanban', ['projectId' => $share->project_id]);
            } elseif ($share->can_notes) {
                return redirect()->route('notes', ['projectId' => $share->project_id]);
            }
        }

        // Everyone else (guest or non-member) → public read-only share view
        $tab = $share->can_kanban ? 'kanban' : 'notes';
        return redirect()->route('share.public', ['token' => $token, 'tab' => $tab]);
    }
}
