<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Response;
use \Google\Cloud\ServiceBuilder;


class ApiController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->middleware('auth');
    }

    private function getGoogleCloudVisionTextAnnotations($image) {

      $keyFilePath = storage_path('app/' . env('KEY_FILE'));
      $projectId = 'even-stranger-things';

      $cloud = new ServiceBuilder([
          'keyFilePath' => $keyFilePath,
          'projectId' => $projectId
      ]);

      $vision = $cloud->vision();

      //$imageResource = file_get_contents($imagePath);

      $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image));

      $image = $vision->image($data, [
        'LABEL_DETECTION'
      ]);

      $labels = $vision->annotate($image)->labels();

      // $annotations = $vision->annotate($image);

      return $labels;

    }

    private function base64ToJpeg($base64String, $outputFile) {

        $ifp = fopen($outputFile, "wb");

        $data = explode(',', $base64String);

        fwrite($ifp, base64_decode($data[1]));
        fclose($ifp);

        return $outputFile;


    }

    private function millitime() {

      $microtime = microtime();
      $comps = explode(' ', $microtime);

      // Note: Using a string here to prevent loss of precision
      // in case of "overflow" (PHP converts it to a double)
      return sprintf('%d%03d', $comps[1], $comps[0] * 1000);

    }

    private function randomString() {

      return substr(md5(rand()), 0, 7);

    }

    public function describeImage(Request $request) {

      $image = $request['image'];

      $labels = $this->getGoogleCloudVisionTextAnnotations($image);

      $results = array();

      foreach ($labels as $label) {
        $results[] = $label->description();
      }

      return Response::json(array('status' => 'success', 'results' => $results));


    }

    public function saveImage(Request $request) {

      // $bucketPath = 'https://s3.' . env('S3_BUCKET_REGION') . '.amazonaws.com/' . env('S3_BUCKET_NAME') . '/';

      $bucketPath = 'https://s3.amazonaws.com/' . env('S3_BUCKET_NAME') . '/';

      $imageName = $this->millitime() . $this->randomString() . '.jpg';

      $image = $request['image'];

      $file = $this->base64ToJpeg($image, $imageName);

      $s3 = \App::make('aws')->createClient('s3');

      try {

        $s3->putObject(array(
            'Bucket'        => env('S3_BUCKET_NAME'),
            'Key'           => $imageName,
            'ContentType'   => 'image/jpeg',
            'SourceFile'    => $file,
            'ACL'           => 'public-read',
        ));

      } catch (S3Exception $e) {

        return Response::json(array('status' => 'exception', 'message' => $e->getMessage()), 400);

      }

      unlink($imageName);

      return Response::json(array('image_path' => $bucketPath . $imageName));

    }

}
