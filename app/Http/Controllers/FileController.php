<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\File;
use App\Models\VehicleImages;
use App\Services\CloudinaryService;
use Throwable;

class FileController extends Controller
{
	private static $s3_client = null;
	private $cloudinary;

	public function __construct(CloudinaryService $cloudinary)
	{
		$this->cloudinary = $cloudinary;
	}

	function sanitizeFilename($filename)
	{
		$fileWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
		$sanitized = Str::slug($fileWithoutExt);
		return $sanitized . '.' . pathinfo($filename, PATHINFO_EXTENSION);
	}

	static function init_client() {
		if ( !self::$s3_client || self::$s3_client == null ) {
			self::$s3_client = new S3Client([
				'version' => 'latest',
				'region'  => env('DO_SPACES_REGION'),
				'endpoint' => env('DO_SPACES_ENDPOINT'),
				'credentials' => [
					'key'    => env('DO_SPACES_KEY'),
					'secret' => env('DO_SPACES_SECRET'),
				],
				'use_path_style_endpoint' => true,
				'http'    => [
					'verify' => false,
				],
			]);
		}
		return self::$s3_client;
	}

	static function get_temp_url( $file_key = '', $expires = '+60 minutes' ) {
		$s3 = self::init_client();
		try {
			$cmd = $s3->getCommand('GetObject', [
				'Bucket' => env('DO_SPACES_BUCKET'),
				'Key'    => $file_key,
			]);
			$request = $s3->createPresignedRequest($cmd, $expires);
			$presigned_url = (string) $request->getUri();
			return $presigned_url;
		} catch (AwsException $e) {

		}
		return false;
	}

    public function uploadImages(Request $request)
    {
		$request->validate([
			'file' => 'required|file|image|mimes:jpeg,jpg,png,gif,webp|max:10240',
			'folder' => 'nullable|string|max:120',
		]);

		$uploadedFile = $request->file('file');
		$imageHash = md5_file($uploadedFile->getPathname());
		$existing = File::where('file_hash', $imageHash)
			->where('provider', 'cloudinary')
			->first();

		if ($existing) {
			return $this->successResponse([
				'key' => $existing->key,
				'id' => $existing->id,
				'image_hash' => $existing->file_hash,
				'url' => $existing->url,
			], 'File already exists');
		}

		try {
			$result = $this->cloudinary->upload($uploadedFile, $request->input('folder'));
			$file = File::create([
				'name' => $uploadedFile->getClientOriginalName(),
				'key' => $result['public_id'],
				'size' => $uploadedFile->getSize(),
				'file_hash' => $imageHash,
				'provider' => 'cloudinary',
				'provider_id' => isset($result['asset_id']) ? $result['asset_id'] : null,
				'url' => $result['secure_url'],
			]);

			return $this->successResponse([
				'key' => $file->key,
				'id' => $file->id,
				'image_hash' => $file->file_hash,
				'url' => $file->url,
			], 'File uploaded successfully');
		} catch (Throwable $exception) {
			Log::warning('Cloudinary image upload failed', [
				'message' => $exception->getMessage(),
			]);
		}

        return response()->errorResponse('Could not upload image');
    }

	public function destroy(Request $request, $file_id )
    {
		$file = File::find($file_id);
		if ($file && $file->key && ! empty($file->key)) {
			try {
				if ($file->provider === 'cloudinary') {
					if (!$this->cloudinary->destroy($file->key)) {
						return response()->errorResponse('Could not delete image from Cloudinary');
					}
				} else {
					$s3 = self::init_client();
					$s3->deleteObject([
						'Bucket' => env('DO_SPACES_BUCKET'),
						'Key' => $file->key,
					]);
				}

				VehicleImages::where('file_id', $file->id)->delete();
				$file->delete();
				return $this->successResponse([
					'key' => $file->key,
					'id' => $file->id,
				], 'File deleted successfully');
			} catch (Throwable $exception) {
				Log::warning('Image deletion failed', [
					'file_id' => $file_id,
					'message' => $exception->getMessage(),
				]);
			}
		}
		return response()->errorResponse('Could not delete image');
	}
}
