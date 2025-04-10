<?php

use App\Helpers\Helper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;

if (! function_exists('errorResponse')){
    function errorResponse($error, $code = 400, $data = [])
    {
        $response = [
            'success' => false,
            'status' => (int) $code,
            'message' => is_array($error) ? $error : [$error],
            'data' => $data, // Ensure data is returned in case you want to provide additional context
        ];

        return response()->json($response, (int) $code);
    }
}

if (! function_exists('successResponse')){
    function successResponse($message, $result = [], $code = 200, $paginate = false)
    {
        $resultData = $result;
        if ($paginate) {
            $resultData = paginate($result);
        }

        $response = [
            'success' => true,
            'status' => (int) $code,
            'message' => is_array($message) ? $message : [$message],
            'data' => $resultData
        ];

        return response()->json($response, (int) $code);
    }
}

if (! function_exists('arrayExcept')){
    function arrayExcept($array, $keys){
        foreach($keys as $key){
            unset($array[$key]);
        }
    return $array;
  }
}

if (! function_exists('paginate')){
    function paginate($data = []){

        $paginationArray = null;
        if ($data) {
            $paginationArray = array ('data'=>$data->items(),'pagination'=>[]);
            $paginationArray['pagination']['total'] = $data->total();
            $paginationArray['pagination']['current'] = $data->currentPage();
            $paginationArray['pagination']['first'] = 1;
            $paginationArray['pagination']['last'] = $data->lastPage();

            if ($data->hasMorePages()) {
                if ($data->currentPage() == 1) {
                    $paginationArray['pagination']['previous'] = 0;
                } else {
                    $paginationArray['pagination']['previous'] = $data->currentPage()-1;
                }
                $paginationArray['pagination']['next'] = $data->currentPage()+1;
            } else {
                $paginationArray['pagination']['previous'] = $data->currentPage()-1;
                $paginationArray['pagination']['next'] =  $data->lastPage();
            }
            if ($data->lastPage() > 1) {
                $paginationArray['pagination']['pages'] = range(1,$data->lastPage());
            } else {
                $paginationArray['pagination']['pages'] = [1];
            }
            $paginationArray['pagination']['from'] = $data->firstItem();
            $paginationArray['pagination']['to'] = $data->lastItem();

            return $paginationArray;
        }
    }

}
