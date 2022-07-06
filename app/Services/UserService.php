<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Interfaces\Services\IUserService;
use App\Models\User;
use Carbon\Carbon;
use CCUFFS\Auth\AuthIdUFFS;
use Exception;
use Illuminate\Support\Facades\Hash;
use App\Helpers\StorageHelper;

class UserService implements IUserService
{
    private UserRepository $repository;
    private BarcodeService $barcodeService;
    private AiPassportPhotoService $aiPassportPhotoService;
    private IdUffsService $idUffsService;

    public function __construct(
        UserRepository         $userRepository,
        BarcodeService         $barcodeService,
        AiPassportPhotoService $aiPassportPhotoService,
        IdUffsService          $idUffsService
    )
    {
        $this->repository = $userRepository;
        $this->barcodeService = $barcodeService;
        $this->aiPassportPhotoService = $aiPassportPhotoService;
        $this->idUffsService = $idUffsService;
    }

    /**
     * @throws Exception
     */
    public function createUserWithIdUFFS($user)
    {
        $user_data_from_auth = $this->idUffsService->authWithIdUFFS($user["uid"], $user["password"]);

        if (!$user_data_from_auth) {
            throw new Exception("The IdUFFS password does not match the one informed.");
        }

        $user_data_from_enrollment = $this->idUffsService->isActive($user["enrollment_id"]);

        if (empty($user_data_from_enrollment)){
            throw new Exception("Enrollment_id is not active at IdUFFS.");
        }

        $user["type"] = $user_data_from_enrollment["type"];
        $user["course"] = $user_data_from_enrollment["course"];
        $user["name"] = $user_data_from_auth["name"];
        $user["email"] = $user_data_from_auth["email"];
        $user["password"] = $user_data_from_auth["password"];

        return $this->createUserBase($user);
    }

    public function createUserWithoutIdUFFS($user)
    {
        $user["enrollment_id"] = bin2hex(random_bytes(5));

        return $this->createUserBase($user);
    }

    /**
     * @param $user
     * @return User
     * @throws Exception
     */
    public function createUserBase($user): User
    {
        $user["profile_photo"] = $this->aiPassportPhotoService->validatePhoto($user["profile_photo"]);
        $user["profile_photo"] = StorageHelper::saveProfilePhoto($user["uid"], $user["profile_photo"]);

        $user["bar_code"] = StorageHelper::saveBarCode($user["uid"], $this->barcodeService->generateBase64($user["enrollment_id"]));

        $user["password"] = Hash::make($user["password"]);

        $user["birth_date"] = Carbon::parse($user["birth_date"]);

        $user["active"] = true;

        $this->repository->createUser($user);

        return $this->getUserByUsername($user["uid"]);
    }

    public function getUserByUsername(string $uid, $withFiles = true): \App\Models\User
    {
        $user = $this->repository->getUserByUsername($uid);

        if (empty($user))
            throw new Exception("User not found.");

        if ($withFiles) {
            $user->profile_photo = StorageHelper::getFile($user->profile_photo);
            $user->bar_code = StorageHelper::getFile($user->bar_code);
        }

        return $user;
    }

    public function getUserByUsernameFirstOrDefault(string $uid, $withFiles = true): ?\App\Models\User
    {
        $user = $this->repository->getUserByUsername($uid);

        if (empty($user))
            return null;

        if ($withFiles) {
            $user->profile_photo = StorageHelper::getFile($user->profile_photo);
            $user->bar_code = StorageHelper::getFile($user->bar_code);
        }

        return $user;
    }

    public function deleteUserByUsername(string $uid): bool
    {
        $user = $this->getUserByUsername($uid);

        StorageHelper::deleteProfilePhoto($user->uid);
        StorageHelper::deleteBarCode($user->uid);

        $user->tokens()->delete();

        return $this->repository->deleteUserByUsername($user->uid);
    }

    public function updateUserWithIdUFFS(string $uid, $data): User{
        $user = $this->getUserByUsername($uid, false);

        if (!in_array($user->type, config('user.users_auth_iduffs'))){
            $app_url = env('app_url');
            throw new Exception("Cannot update user through this endpoint, please use {$app_url}/api/user.");
        }

        return $this->updateUser($user, $data);
    }

    public function updateUserWithoutIdUFFS(string $uid, $data): User{


        $user = $this->getUserByUsername($uid, false);

        if (!in_array($user->type, config('user.users_auth_locally'))){
            $app_url = env('app_url');
            throw new Exception("Cannot update user through this endpoint, please use {$app_url}/api/user/iduffs.");
        }

        return $this->updateUser($user, $data);
    }


    public function updateUser(User $user, $data): User
    {
        if (isset($data["birth_date"])){
            $data["birth_date"] = Carbon::parse($data["birth_date"]);
        }

        if (isset($data["enrollment_id"]) and $data["enrollment_id"] != $user->enrollment_id) {
            StorageHelper::deleteBarCode($user->uid);
            $data["bar_code"] = StorageHelper::saveBarCode($user->uid, $this->barcodeService->generateBase64($data["enrollment_id"]));
        }

        if (isset($data["profile_photo"])) {
            StorageHelper::deleteProfilePhoto($user->uid);
            $data["profile_photo"] = $this->aiPassportPhotoService->validatePhoto($data["profile_photo"]);
            $data["profile_photo"] = StorageHelper::saveProfilePhoto($user->uid, $data["profile_photo"]);
        }

        $this->repository->updateUserByUsername($user->uid, $data);

        return $this->getUserByUsername($user->uid);
    }

    public function deactivateUser(string $uid, $data): User
    {
        $user = $this->getUserByUsername($uid, false);

        $this->repository->updateUserByUsername($user->uid, $data);

        return $this->getUserByUsername($uid);
    }

}
