<?php

namespace App\Repository;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;

class CacheWorkerRepository extends Filesystem
{
    private const REQUIRED_FILEDS = ['name', 'phone', 'email'];

    private const ALL_FIELDS = ['id', 'name', 'company', 'phone', 'email', 'date_of_born', 'photo'];

    private $cacheHandler;

    /**
     * @var int
     */
    private static $maxIndex;

    /**
     * @var int
     */
    private static $needleId;

    private $cache;

    /**
     * @var array[]
     */
    private $initSetOfData = [
        [1, 'Белов Василий  Иванович', 'ФондИнвест', '+79015458963', 'test@yandex.ru', '1998-10-10', ''],
        [2, 'Орлова Антонина  Прокофьевна', 'АльфаБанк', '+79472356147', 'gg231@ya.ru', '1995-07-05', 'https://fastly.picsum.photos/id/626/100/100.jpg?hmac=tJ8TAiUwX_FHayxS6IkBV29op4S6Im_KACZlgqfgJok'],
        [3, 'Григорьев Борис Васильевич', 'Сбербанк', '+79472356148', '123ff@yahoo.com', '200-01-10', 'https://fastly.picsum.photos/id/267/100/100.jpg?hmac=Cl1aDzpO5NI099c8Ro13FdsM5_KvjKSyKOHOZNQ_prM'],
        [4, 'Фролова Лариса Ивановна', 'Пятерочка', '+79472356137', 'fer23@mail.ru', '1999-12-10', ''],
        [5, 'Гончарова Анна Борисовна', 'Яндекс', '+79472356847', 'gsd3441sd@mail.ru', '1985-04-20', 'https://fastly.picsum.photos/id/300/100/100.jpg?hmac=tEBhymSuoJcCDVDPtvJzkFTcwDOKFx9peVXQOnSwtbo'],
    ];

    /**
     * SessionWorkerRepository constructor.
     */
    public function __construct()
    {
        $this->cache = new FilesystemAdapter();
        $this->cacheHandler = $this->cache->getItem('db.notebook');;
        if (!$this->cacheHandler->isHit()) {
            $this->cacheHandler->set($this->initSetOfData);
            $this->cache->save($this->cacheHandler);
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function readAll(): array
    {
        if (empty($this->cacheHandler->get())) {
            throw new \Exception('no data set');
        }

        return array_map('self::structureDataJsonObject', $this->cacheHandler->get());
    }

    /**
     * @param int $noteId
     * @return array
     * @throws \Exception
     */
    public function readNoteById(int $noteId): array
    {
        self::$needleId = $noteId;

        if (empty($this->cacheHandler->get())) {
            throw new \Exception('no data set');
        }

        $result = array_filter($this->cacheHandler->get(), static function($item) {
            return $item[0] === self::$needleId;
        });

        if (empty($result)) {
            throw new \Exception("data not found for id: {$noteId}");
        }

        return array_map('self::structureDataJsonObject', array_values($result));
    }

    /**
     * @param int $noteId
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function writeNoteByIdToStack(int $noteId, array $data): bool
    {
        self::$needleId = $noteId;

        if (empty($data) || empty($noteId)) {
            throw new \Exception('nothing to set in store, or id not configured');
        }

        $result = array_filter($this->cacheHandler->get(), static function($item) {
            return $item[0] === self::$needleId;
        });

        $index = array_keys($result)[0];

        $newData = $this->cacheHandler->get();
        $newData[$index] = self::extractDataFromJSONObject($data, $noteId);

        $this->cacheHandler->set($newData);

        return $this->cache->save($this->cacheHandler);
    }

    /**
     * @param int $noteId
     * @return bool
     * @throws \Exception
     */
    public function deleteNoteById(int $noteId): bool
    {
        self::$needleId = $noteId;

        if (empty($noteId)) {
            throw new \Exception('id not configured');
        }

        $allNotes = $this->cacheHandler->get();

        $result = array_filter($allNotes, static function($item) {
            return $item[0] === self::$needleId;
        });



        $index = array_keys($result)[0];

        if (isset($allNotes[$index])) {
            unset($allNotes[$index]);

            $allNotes = array_values($allNotes);

            $this->cacheHandler->set($allNotes);
            return $this->cache->save($this->cacheHandler);
        }

        return false;
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    public function writeAll(array $data): void
    {
        $currentData = $this->cacheHandler->get() ?? [];

        if (!empty($currentData)) {
            $ids = array_map(static function($item) {
                return $item[0];
            }, $currentData);
        }

        self::$maxIndex = max($ids);

        try {
            $extractNewData = array_map('self::extractDataFromJSONObject', $data);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        $this->cacheHandler->set(array_merge($currentData, $extractNewData));
        $this->cache->save($this->cacheHandler);
    }

    /**
     * @param array $cell
     * @return array
     */
    private static function structureDataJsonObject(array $cell): array
    {
        return array_combine(self::ALL_FIELDS, $cell);
    }

    /**
     * @param
     * @param array $cell
     * @param null|int $preSetId
     * @return array
     * @throws \Exception
     */
    private static function extractDataFromJSONObject(array $cell, ?int $preSetId = null): array
    {
        $fields = array_keys($cell);

        if (count(array_intersect($fields, self::REQUIRED_FILEDS)) !== 3) {
            throw new \Exception('not fill required data');
        }

        self::$maxIndex++;

        return [
            $preSetId ?? self::$maxIndex,
            $cell['name'],
            $cell['company'] ?? '',
            $cell['phone'],
            $cell['email'],
            $cell['date_of_born'] ?? '',
            $cell['photo'] ?? '',
        ];
    }
}