<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;
use App\Http\Requests\BaseRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUserProfileRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        $userId = Auth::id(); // or use $this->user()->id if you prefer

        return [
            'name' => 'required|string|max:255',
            'username' => 'sometimes|required|max:255|regex:/^[^\s]*$/|unique:users,username,' . $userId, // Ignore the current user's username
            'phone' => 'sometimes|nullable|string|max:15',
            'address' => 'required',
            'date_of_birth' => 'sometimes|date|before:today', // Ensure date_of_birth is a valid date and not a future date
            'gender' => 'sometimes|string|in:male,female,other', // Allow only specified genders
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:3072', // Validate profile_image as an image and restrict file types and size (2MB max)
        ];
    }
    
    public function withValidator($validator)
    {
        $validator->sometimes('avatar', 'required', function ($input) {
            return is_null($input->avatar);
        });
    }

    /**
     * Custom error messages for validation.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'date_of_birth.before' => 'The date of birth must be a date before today.',
            'gender.in' => 'The selected gender is invalid.',
            'username.regex' => 'The username must not contain spaces.',
            'username.unique' => 'The username has already been taken.',
            'avatar.max' => 'The avatar must not be greater than 3MB.',
            // Custom message for file size validation
        ];
    }
}
