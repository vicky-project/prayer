<?php

namespace Modules\Prayer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
{
  public function authorize() {
    return true;
  }

  public function rules() {
    return [
      'city' => 'sometimes|string|max:255',
      'lat' => 'required_without:city|numeric|between:-90,90',
      'lon' => 'required_without:city|numeric|between:-180,180',
    ];
  }
}