<?php

namespace App\Controller;

use App\Repository\CacheWorkerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiNotebookController extends AbstractController
{
    private const TOKEN_API = '124e45a4d54c5b5e4f54';

    /**
     * @Route("/api/v1/notebook/", methods="GET", name="get_content_book")
     *
     * @param Request $request
     * @param CacheWorkerRepository $repository
     *
     * @return JsonResponse
     */
    public function getNotebookEntries(
        Request $request,
        CacheWorkerRepository $repository
    ): JsonResponse {
        try {
            $result = $repository->readAll();
        } catch (\Exception $e) {
            return $this->json([
                'mess' => $e->getMessage()
            ], 500);
        }

        if ($request->query->has('offset')) {
            $offset = $request->query->getInt('offset');
            $limit = $request->query->getInt('limit', 6);

            $pagination = [];

            for ($i = $offset; $i < $offset + $limit; $i++) {
                array_push($pagination, $result[$i]);
            }

            return $this->json($pagination);
        }

        return $this->json($result);
    }

    /**
     * @Route("/api/v1/notebook/", methods="POST", name="set_content_book")
     *
     * @param Request $request
     * @param CacheWorkerRepository $repository
     *
     * @return JsonResponse
     */
    public function setNotebookEntries(
        Request $request,
        CacheWorkerRepository $repository,
        LoggerInterface $logger
    ): JsonResponse {
        if ($request->query->get('token') !== self::TOKEN_API) {
            return $this->json([
                'mess' => 'Access disallowed. For work need access token'
            ], 403);
        }

        try {
            $jsonData = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\Exception $e) {
            $logger->error('some happen wrong with JSON');

            return $this->json(['mess' => 'incorrect format'], 500);
        }

        try {
            $repository->writeAll($jsonData);
        } catch (\Exception $e) {
            $logger->error('some happen wrong with JSON');
            return $this->json(['mess' => $e->getMessage()], 500);
        }

        return $this->json(['mess' => 'all data accepted', 'count' => count($jsonData)]);
    }

    /**
     * @Route("/api/v1/notebook/{id}", methods="GET", requirements={"id"="\d+"}, name="get_concret_content_book")
     *
     * @param int $id
     * @param CacheWorkerRepository $repository
     *
     * @return JsonResponse
     */
    public function getNotebookEntry(
        int $id,
        CacheWorkerRepository $repository
    ): JsonResponse {
        try {
            $findData = $repository->readNoteById($id);
        } catch (\Exception $e) {
            return $this->json([
                'mess' => $e->getMessage()
            ], 500);
        }

        return $this->json($findData);
    }

    /**
     * @Route("/api/v1/notebook/{id}", methods="POST", requirements={"id"="\d+"}, name="set_concret_content_book")
     *
     * @param Request $request
     * @param int $id
     * @param CacheWorkerRepository $repository
     * @param LoggerInterface $logger
     *
     * @return JsonResponse
     */
    public function setNotebookEntry(
        Request $request,
        int $id,
        CacheWorkerRepository $repository,
        LoggerInterface $logger
    ): JsonResponse
    {
        try {
            $jsonData = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (\Exception $e) {
            $logger->error('some happen wrong with JSON');

            return $this->json(['mess' => 'incorrect format'], 500);
        }

        try {
            $result = $repository->writeNoteByIdToStack($id, $jsonData);
        } catch (\Exception $e) {
            return $this->json([
                'mess' => $e->getMessage()
            ], 500);
        }

        return $this->json([
            'mess' => $result ? 'data was saved success' : 'some error while data was saved'
        ], $result ? 200 : 500);
    }

    /**
     * @Route("/api/v1/notebook/{id}", methods="DELETE", requirements={"id"="\d+"}, name="delete_concret_content_book")
     *
     * @param int $id
     * @param CacheWorkerRepository $repository
     * @param LoggerInterface $logger
     * @return JsonResponse
     */
    public function deleteNoteEntry(
        int $id,
        CacheWorkerRepository $repository,
        LoggerInterface $logger
    ): JsonResponse {
        try {
            $result = $repository->deleteNoteById($id);
        } catch (\Exception $e) {
            $logger->error('deletion error', [
                'id' => $id
            ]);
        }

        return $this->json([
            'mess' => $result ? 'entry success deleted' : 'deletion error'
        ], $result ? 200 : 500);
    }
}