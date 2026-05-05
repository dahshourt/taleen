<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Repository\Director\DirectorRepository;
use App\Models\Director;

class DirectorService
{
    protected $directorRepository;

    public function __construct(DirectorRepository $directorRepository)
    {
        $this->directorRepository = $directorRepository;
    }

    public function getAllDirectors(bool $paginated = false)
    {
        return $this->directorRepository->getAll($paginated);
    }

    public function findDirector($id): Director
    {
        return $this->directorRepository->find($id);
    }

    public function createDirector(array $data): Director
    {
        return $this->directorRepository->create($data);
    }

    public function updateDirector(array $data, $id): Director
    {
        return $this->directorRepository->update($data, $id);
    }

    public function updateDirectorStatus($id): bool
    {
        $director = $this->findDirector($id);

        $new_status = (int) ! $director->status;

        return $this->directorRepository->updateStatus((string) $new_status, $id);
    }
}
