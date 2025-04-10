<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseRequest;

class LoginRequest extends BaseRequest
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
            'email' => 'required|email',
            'role_id' => 'required|in:2,3',
            'password' => 'required',
            'device_token'=>'sometimes|required',
            'device_type'=>'sometimes|required_with:device_token',
            'device_info'=>'sometimes',
        ];
    }
}
