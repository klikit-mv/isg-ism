<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\StudentService;
use App\Services\UserService;
use App\Support\StudentValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Public self-registration. Accounts stay inactive until a leader verifies them.
 */
class RegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, StudentService $students): RedirectResponse
    {
        $request->merge(StudentValidation::prepare($request->only('national_id')));
        $data = $request->validate(StudentValidation::rules(publicRegistration: true));

        $students->register($data);

        return redirect()->route('login')->with('success', 'Thank you for registering. A leader will verify your details before you can sign in.');
    }

    public function createParent(): View
    {
        return view('auth.register-parent');
    }

    public function storeParent(Request $request, UserService $users): RedirectResponse
    {
        $request->merge(['national_id' => strtoupper(trim((string) $request->input('national_id')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'national_id' => ['required', 'string', 'max:64', Rule::unique('users', 'national_id')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'pin' => ['required', 'string', 'min:4', 'max:32', 'confirmed'],
            'children' => ['required', 'array', 'min:1'],
            'children.*' => ['required', 'string', 'max:64'],
        ], [
            'children.required' => 'Add at least one child by National ID.',
        ]);

        $users->registerParent($data, $data['children']);

        return redirect()->route('login')->with('success', 'Thank you for registering. A leader will verify your account and children before you can sign in.');
    }
}
