<?php

namespace App\Http\Controllers;

use App\Services\User\AvatarMetadataService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AvatarMetadataService $avatars): View
    {
        $user = $request->user();

        return view('pages.dashboard', [
            'user' => $user,
            'avatarUrl' => $avatars->url($user),
        ]);
    }
}
