<?php

namespace App\Http\Requests;
use Illuminate\Validation\Rules\Password;
use App\Http\Requests\BaseRequest;

class RegisterRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'email' => 'required|email|unique:users',
            'username' => 'required|unique:users|max:255|regex:/^[^\s]*$/',
            'password' => [
                'required',
                Password::min(6)
                  ->letters()
                  ->mixedCase()
                  ->numbers()
                  ->symbols()
            ],
            'password_confirmation' => 'required|same:password',
            
            'role_id' => 'required|in:2,3', // Ensure role_id is either 2 or 3
            'phone' => 'sometimes',
            'device_id' => 'required|string',
            'date_of_birth' => 'sometimes|date|before:today', // Ensure date_of_birth is a valid date and not a future date
            'gender' => 'sometimes|string|in:male,female,other', // Adjust valid gender values as needed
            //'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048', // Validate profile_image as an image and restrict file types and size
        ];
    }
    public function messages()
    {
        return [
             'username.regex' => 'The username must not contain spaces.', // Custom message for spaces
             'email.unique' => 'The email has already been taken.',
            'username.unique' => 'The username has already been taken.',
        ];
    }
}
