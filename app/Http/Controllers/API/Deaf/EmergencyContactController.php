<?php

namespace App\Http\Controllers\API\Deaf;

use App\Http\Controllers\API\BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\Models\EmergencyContact;
use App\Models\User;

class EmergencyContactController extends BaseController
{
    // Create a new emergency contact
    public function create(Request $request)
    {
        try {
            // Validate request data
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'required|string|max:15',
                'relation' => 'required|string|max:100',
            ]);

            // Create a new emergency contact
            $contact = EmergencyContact::create([
                'user_id' => $this->userID,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'relation' => $request->relation,
            ]);

            return successResponse('Emergency contact created successfully', $contact, 201);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }

    // Get all emergency contacts for the current user
    public function getUserContacts()
    {
        try {
            $contacts = EmergencyContact::where('user_id', $this->userID)->get();

            return successResponse('Emergency contacts retrieved successfully', $contacts, 200);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }

    // Update an existing emergency contact
    public function update(Request $request, $id)
    {
        try {
            // Validate request data
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'required|string|max:15',
                'relation' => 'required|string|max:100',
            ]);

            // Find the contact and update it
            $contact = EmergencyContact::where('id', $id)->where('user_id', $this->userID)->firstOrFail();
            $contact->update($request->only(['name', 'email', 'phone', 'relation']));

            return successResponse('Emergency contact updated successfully', $contact, 200);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }

    // Delete an emergency contact
    public function delete($id)
    {
        try {
            $contact = EmergencyContact::where('id', $id)->where('user_id', $this->userID)->firstOrFail();
            $contact->delete();

            return successResponse('Emergency contact deleted successfully', null, 200);
        } catch (\Throwable $th) {
            return errorResponse($th->getMessage(), 500);
        }
    }
}