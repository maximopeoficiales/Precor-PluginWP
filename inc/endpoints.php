<?php

use mjohnson\utility\TypeConverter;
use Rakit\Validation\Validator;
//utilidades
function mfSetResponse($respuesta, $detalle, $data, $status, $json = false)
{
     $typeApp = $json ? "json" : "xml";
     $array = array(
          'RESPONSE' => $respuesta,
          'DETAILS' => $detalle,
          'STATUS' => $status,
          'DATA' => $data,
     );
     Header("Content-Type: text/$typeApp; charset=utf-8", true, $status);
     return $json ? $array : mfArrayToXML($array);
}
function mfIsAuthorized($user, $password)
{
     if (true) {
          return true;
     } else {
          return false;
     }
}
function mfNotAuthorized()
{
     return mfSendResponse(["value" => 0, "message" => "Error en la autenticacion"]);
}
function mfXmlToArray($url)
{
     $xml = file_get_contents($url);
     $array = TypeConverter::xmlToArray($xml, TypeConverter::XML_MERGE);
     return $array;
}
function mfArrayToXML($data)
{

     $data = TypeConverter::toXml($data);
     return $data;
}
//funciones que retorna respuesta
function mfGetUnitWithMetadata($metadata, $unit)
{
     $value = "";
     foreach ($metadata as $key) {
          if ($key["key"] == "unit") {
               $valueUnit = $key["value"];
               $extract = explode(":", $valueUnit);
               if ($extract[0] == $unit) {
                    $value = $extract[1];
               }
          }
     }
     return $value;
}
function mfUpdateProductWithSku($sku, $dataUpdated)
{
     $woo = max_functions_getWoocommerce();
     $findMaterial = $woo->get("products", ["sku" => $sku]);
     $response = $woo->put("products/" . $findMaterial[0]->id, $dataUpdated);
     return $response;
}
function mfCreateProductWoo($data)
{

     $woo = max_functions_getWoocommerce();
     $material = $data["material"];
     $weight = number_format($material["weight"], 2, ".", "");
     $sku = $material["sku"];
     $dataSend = [
          'name' => $material["name"],
          'sku' => $sku,
          'weight' => $weight,
          "metadata" => [],
     ];
     if ($material["unit"] !== "kg") {
          $dataSend["meta_data"] = [
               [
                    "key" => "unit",
                    "value" => $material['unit'] . ":" . $weight
               ]
          ];
     }

     try {
          $response = $woo->post('products', $dataSend); //devuelve un objeto
          if (!$response->id == null) {
               return [
                    "value" => 1,
                    "data" => $response,
                    "message" => "Registro de Material Exitoso",
               ];
          }
     } catch (\Throwable $th) {
          return [
               "value" => 0,
               "message" => "EL SKU: $sku ya existe",
          ];
     }
}
function mfUpdateProductWoo($sku, $data)
{
     $material = $data["material"];
     //validations
     $validator = new Validator;
     $params = ["sku" => $sku, "stock" => $material["stock"]];
     $validation = $validator->make($params, [
          'sku'                  => 'required|min:1|max:12',
          'stock'                  => 'required|min:1|max:5',
     ]);
     $validation->validate();
     if ($validation->fails()) {
          $errors = $validation->errors();
          $response = ["value" => 0, "message" => $errors->firstOfAll()];
          return   $response;
     }
     //updated
     $dataUpdated = [
          "manage_stock" => true,
          "stock_quantity" => $material["stock"],
     ];
     try {
          $response = mfUpdateProductWithSku($sku, $dataUpdated);
          return [
               "value" => 2,
               "message" => "Material con sku: $sku actualizado",
               "data" => json_decode(json_encode($response), true)
          ];
          /*    } */
     } catch (\Throwable $th) {
          return [
               "value" => 0,
               "message" => "El material con el sku: $sku no existe",
          ];
     }
}
function mfSendResponse($response)
{
     $json = true;
     if ($response["value"] !== 0) {
          //si no es un error
          $data = mfSetResponse($response["value"], $response["message"],  $response["data"], 200, $json);
          if ($json)
               return $data;
          else
               print($data);
     } else {
          //si ocurrio un error
          $data = mfSetResponse($response["value"], $response["message"], null, 400, $json);
          if ($json)
               return $data;
          else
               print($data);
     }
}

//callbacks de endpoints
function mfCreateMaterial($params)
{
     $data = mfXmlToArray("php://input"); //recogo data xml
     return  mfValidationGeneralAuth($data, $params, function ($data) {
          $material = $data["material"];
          $validateMaterial = mfValidateMaterialFields($material); //validacion de security
          if ($validateMaterial["validate"]) {
               // $created = mfCreateProductWoo($data);
               // return mfSendResponse($created);
               return mfSendResponse(["value" => 1, "message" => "Todo Correcto"]);
          } else {
               return mfSendResponse($validateMaterial["errors"]);
          }
     }, ["security" => "required", "material" => "required"]);
}

function mfUpdateMaterial($params)
{
     $data = mfXmlToArray("php://input"); //recogo data xml
     return  mfValidationGeneralAuth($data, $params, function ($data, $params) {
          // $sku = $params["sku"];
          // $updated = mfUpdateProductWoo($sku, $data);
          // return mfSendResponse($updated);
          return mfSendResponse(["value" => 1, "message" => "Todo Correcto"]);
     }, ["security" => "required", "material" => "required"]);
}


//EndPoints
// http://maxco.punkuhr.test/wp-json/max_functions/v1/materials (POST)
add_action("rest_api_init", function () {
     register_rest_route("max_functions/v1", "/materials", array(
          "methods" => "POST",
          "callback" => "mfCreateMaterial",
          'args'            => array(),
     ));
});
// http://maxco.punkuhr.test/wp-json/max_functions/v1/materials/sku (POST)
add_action("rest_api_init", function () {
     register_rest_route("max_functions/v1", "/materials/(?P<sku>\d+)", array(
          "methods" => "POST",
          "callback" => "mfUpdateMaterial",
          'args'            => array(),
     ));
});

//validations
function mfValidationGeneralAuth($data, $params = null, $function, $validations = [])
{
     $validateBody = mfValidateDataEmpty($data, $validations); //validacion de data
     if ($validateBody["validate"]) {
          $security = $data["security"];
          $validateSecurity = mfValidateSecurityFields($security); //validacion de security
          if ($validateSecurity["validate"]) {
               if (mfIsAuthorized($security["user"], $security["pass"])) {
                    return $function($data, $params);
               } else {
                    return mfNotAuthorized();
               }
          } else {
               return mfSendResponse($validateSecurity["errors"]);
          }
     } else {
          return mfSendResponse($validateBody["errors"]);
     }
}
function mfValidateDataEmpty($data, $validations)
{
     $validator = new Validator;
     $validation = $validator->make($data, $validations);
     $validation->validate();
     if ($validation->fails()) {
          // handling errors
          $errors = $validation->errors();
          $response = ["value" => 0, "message" => $errors->firstOfAll()];
          return ["validate" => false, "errors" => $response];
     } else {
          return ["validate" => true];
     }
}
function mfValidateSecurityFields($security)
{
     $validator = new Validator;
     // make it
     $validation = $validator->make($security, [
          'user'                  => 'required|min:1|max:11',
          'pass'              => 'required|min:1|max:13',
     ]);
     $validation->validate();
     if ($validation->fails()) {
          // handling errors
          $errors = $validation->errors();
          $response = ["value" => 0, "message" => $errors->firstOfAll()];
          return ["validate" => false, "errors" => $response];
     } else {
          return ["validate" => true];
     }
}
function mfValidateMaterialFields($material)
{
     $validator = new Validator;
     // make it
     $validation = $validator->make($material, [
          'sku'                  => 'required|min:1|max:12',
          'name'              => 'required|min:1|max:40',
          'unit'              => 'required|min:1|max:3',
          'weight'              => 'required|min:1|max:6',
     ]);
     $validation->validate();
     if ($validation->fails()) {
          // handling errors
          $errors = $validation->errors();
          $response = ["value" => 0, "message" => $errors->firstOfAll()];
          return ["validate" => false, "errors" => $response];
     } else {
          return ["validate" => true];
     }
}
q