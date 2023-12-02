<?php
/**
 * Model for the entries table
 *
 * @author gRegor Morrill, https://gregorlove.com
 * @copyright 2022 gRegor Morrill
 * @license https://opensource.org/licenses/MIT MIT
 * @since 0.1.0
 */

declare(strict_types=1);

namespace App\Model;

use DateTime;
use ORM;
use Mwhite\PhpIsbn\Isbn;

class Entry
{
    private $table_name = 'entries';

    public function add(array $data): ?array
    {
        $dt_published = new DateTime();
        if (array_key_exists('published', $data) && $data['published']) {
            $dt_published = new DateTime($data['published']);
        }

        $record = ORM::for_table($this->table_name)->create();
        $record->isbn = $data['isbn'] ?? '';
        $record->doi = $data['doi'] ?? '';
        $record->user_id = $data['user_id'];
        $record->published = $dt_published->format('Y-m-d H:i:s');
        $record->tz_offset = $this->tz_offset_to_seconds($data['tz_offset']);
        $record->read_status = $data['read_status'] ?? '';
        $record->title = $data['title'] ?? '';
        $record->authors = $data['authors'] ?? '';
        $record->category = $data['category'] ?? '';
        $record->visibility = $data['visibility'] ?? '';

        if ($record->save()) {
            return $this->get((int) $record->id);
        }

        return null;
    }

    public function update(int $id, array $data): ?array
    {
        $record = ORM::for_table($this->table_name)
            ->where('id', $id)
            ->find_one();

        if (!$record) {
            return null;
        }

        if (array_key_exists('canonical_url', $data)) {
            $record->canonical_url = $data['canonical_url'] ?? '';
        }

        if (array_key_exists('micropub_success', $data)) {
            $record->micropub_success = $data['micropub_success'] ?? 0;
        }

        if (array_key_exists('micropub_response', $data)) {
            $record->micropub_response = $data['micropub_response'] ?? '';
        }

        if ($record->save()) {
            return $this->get($id);
        }

        return null;
    }

    public function get(int $id): ?array
    {
        $record = ORM::for_table($this->table_name)
            ->where('id', $id)
            ->find_one();

        if ($record) {
            return $record->as_array();
        }

        return null;
    }

    /**
     * Get an entry by id + user_id
     */
    public function getUserEntry(int $id, int $user_id): ?array
    {
        $record = ORM::for_table($this->table_name)
            ->table_alias('e')
            ->select_many_expr([
                'e.id',
                'e.read_status',
                'e.title',
                'e.authors',
                'e.isbn',
                'e.doi',
                'e.url',
                'e.category',
                'e.published',
                'e.tz_offset',
                'e.visibility',
                'e.content',
                'e.canonical_url',
                'e.micropub_success',
                'e.user_id',
                'profile_name' => 'u.name',
                'profile_url' => 'u.url',
                'profile_photo_url' => 'u.photo_url',
                'u.profile_slug',
                'user_type' => 'u.type',
            ])
            ->join('users', ['e.user_id', '=', 'u.id'], 'u')
            ->where('e.id', $id)
            ->where('e.user_id', $user_id)
            ->find_one();

        if ($record) {
            return $record->as_array();
        }

        return null;
    }

    public function getUserLatestEntry(int $user_id): ?array
    {
        $record = ORM::for_table($this->table_name)
            ->where('user_id', $user_id)
            ->order_by_desc('published')
            ->limit(1)
            ->find_one();

        if ($record) {
            return $record->as_array();
        }

        return null;
    }

    public function findByUser(
        int $user_id,
        ?int $before = null,
        int $limit = 10
    ): array {
        $records = ORM::for_table($this->table_name)
            ->table_alias('e')
            ->select_many_expr([
                'e.id',
                'e.read_status',
                'e.title',
                'e.authors',
                'e.isbn',
                'e.doi',
                'e.url',
                'e.category',
                'e.published',
                'e.tz_offset',
                'e.visibility',
                'e.content',
                'e.canonical_url',
                'e.micropub_success',
                'e.user_id',
                'profile_name' => 'u.name',
                'profile_url' => 'u.url',
                'profile_photo_url' => 'u.photo_url',
                'u.profile_slug',
                'user_type' => 'u.type',
            ])
            ->join('users', ['e.user_id', '=', 'u.id'], 'u')
            ->where('e.user_id', $user_id)
            ->where_not_equal('e.visibility', 'unlisted');

        if ($before) {
            $records->where_lt('id', $before);
        }

        $records = $records->order_by_desc('published')
            ->limit($limit);

        return $records->find_array();
    }

    public function findByIsbn(
        string $isbn,
        ?int $before = null,
        int $limit = 10
    ): array {
        $records = ORM::for_table($this->table_name)
            ->table_alias('e')
            ->select_many_expr([
                'e.id',
                'e.read_status',
                'e.title',
                'e.authors',
                'e.isbn',
                'e.doi',
                'e.url',
                'e.category',
                'e.published',
                'e.tz_offset',
                'e.visibility',
                'e.content',
                'e.canonical_url',
                'e.micropub_success',
                'e.user_id',
                'profile_name' => 'u.name',
                'profile_url' => 'u.url',
                'profile_photo_url' => 'u.photo_url',
                'u.profile_slug',
                'user_type' => 'u.type',
            ])
            ->join('users', ['e.user_id', '=', 'u.id'], 'u')
            ->where('e.isbn', $isbn)
            ->where_not_equal('e.visibility', 'unlisted');

        if ($before) {
            $records->where_lt('id', $before);
        }

        $records = $records->order_by_desc('published')
            ->limit($limit);

        return $records->find_array();
    }

    public function findByUserExport(int $user_id): array
    {
        return ORM::for_table($this->table_name)
            ->table_alias('e')
            ->select_many_expr([
                'e.id',
                'e.read_status',
                'e.title',
                'e.authors',
                'e.isbn',
                'e.doi',
                'e.url',
                'e.category',
                'e.published',
                'e.tz_offset',
                'e.visibility',
                'e.content',
                'e.canonical_url',
                'e.micropub_success',
                'e.user_id',
                'profile_name' => 'u.name',
                'profile_url' => 'u.url',
                'profile_photo_url' => 'u.photo_url',
                'u.profile_slug',
                'user_type' => 'u.type',
            ])
            ->join('users', ['e.user_id', '=', 'u.id'], 'u')
            ->where('e.user_id', $user_id)
            ->order_by_desc('published')
            ->find_array();
    }

    /**
     * Get number of public posts created during the specified timeframe
     */
    public function getNewCount(
        string $start_date,
        string $end_date
    ): int {
        $dt_start = new DateTime($start_date);
        $dt_end = new DateTime($end_date);
        $dt_end->setTime(23, 59, 59);

        return ORM::for_table($this->table_name)
            ->where('visibility', 'public')
            ->where_gte('published', $dt_start->format('Y-m-d'))
            ->where_lte('published', $dt_end->format('Y-m-d'))
            ->count();
    }

    /**
     * Public entries created during the specified timeframe
     */
    public function findNew(
        string $start_date,
        string $end_date,
        ?int $user_id = null
    ): array {
        $dt_start = new DateTime($start_date);
        $dt_end = new DateTime($end_date);
        $dt_end->setTime(23, 59, 59);

        $records = ORM::for_table($this->table_name)
            ->select_many([
                'id',
                'user_id',
                'title',
                'isbn',
                'doi',
                'canonical_url',
            ])
            ->where('visibility', 'public')
            ->where_gte('published', $dt_start->format('Y-m-d'))
            ->where_lte('published', $dt_end->format('Y-m-d'))
            ->order_by_desc('published');

        if ($user_id) {
            $records = $records->where('user_id', $user_id);
        }

        return $records->find_array();
    }

    /**
     * Find a list of distinct titles from public posts during
     * the specified timeframe
     *
     * ISBN and DOI are used to determine distinct posts.
     * If neither identifier, the title is added as distinct.
     */
    public function findDistinct(
        string $start_date,
        string $end_date
    ): array {
        $dt_start = new DateTime($start_date);
        $dt_end = new DateTime($end_date);
        $dt_end->setTime(23, 59, 59);

        $results = [];

        $records = ORM::for_table($this->table_name)
            ->select_many([
                'id',
                'title',
                'authors',
                'isbn',
                'doi',
                // 'user_id',
                // 'canonical_url',
            ])
            ->where('visibility', 'public')
            ->where_gte('published', $dt_start->format('Y-m-d'))
            ->where_lte('published', $dt_end->format('Y-m-d'))
            ->order_by_asc('published')
            ->find_array();

        foreach ($records as $entry) {
            if ($entry['isbn'] && ($isbn_ten = Isbn::to10($entry['isbn'], true))) {
                $entry['isbn'] = $isbn_ten;
            }

            $index = $entry['isbn'];
            if (!$index) {
                # fallback to doi or no_id for results index
                if ($entry['doi']) {
                    $index = $entry['doi'];
                } else {
                    $index = 'no_id' . $entry['id'];
                }
            }

            if (array_key_exists($index, $results)) {
                $results[$index]['count'] += 1;
            } else {
                $results[$index] = array_merge(
                    $entry,
                    ['count' => 1]
                );
            }
        }

        return $results;
    }

    /**
     * If there are older entries, return the ID to use in the
     * query paramaeter `before`
     * Returns null if there are no older entries
     */
    public function getOlderId(int $user_id, int $id): ?int
    {
        $count = ORM::for_table($this->table_name)
            ->where('user_id', $user_id)
            ->where_lt('id', $id)
            ->order_by_desc('published')
            ->count();

        if ($count > 0) {
            return $id;
        }

        return null;
    }

    /**
     * If there are newer entries, return the ID to use in the
     * query paramaeter `before`
     * Returns null if newer entries are on the first page
     */
    public function getNewerId(int $user_id, int $id, int $limit = 10): ?int
    {
        $record = ORM::for_table($this->table_name)
            ->where('user_id', $user_id)
            ->where_gt('id', $id)
            ->order_by_asc('published')
            ->offset($limit)
            ->find_one();

        if ($record) {
            return (int) $record->id;
        }

        return null;
    }

    /**
     * If there are newer entries by ISBN, return the ID to use in the
     * query paramaeter `before`
     * Returns null if newer entries are on the first page
     */
    public function getNewerByIsbn(string $isbn, int $id, int $limit = 10): ?int
    {
        $record = ORM::for_table($this->table_name)
            ->where('isbn', $isbn);

        return $this->getNewer($record, $id, $limit);
    }

    /**
     * If there are older entries by ISBN, return the ID to use in the
     * query paramaeter `before`
     * Returns null if there are no older entries
     */
    public function getOlderByIsbn(string $isbn, int $id): ?int
    {
        $record = ORM::for_table($this->table_name)
            ->where('isbn', $isbn);

        return $this->getOlder($record, $id);
    }

    /**
     * Helper method that takes a base ORM query and
     * adds the conditions common to all getNewer* queries.
     */
    private function getNewer(ORM $record, int $id, int $limit): ?int
    {
        $record->where_gt('id', $id)
            ->order_by_asc('published')
            ->offset($limit)
            ->find_one();

        if ($record) {
            return (int) $record->id;
        }

        return null;
    }

    /**
     * Helper method that takes a base ORM query and
     * adds the conditions common to all getOlder* queries.
     */
    private function getOlder(ORM $record, int $id): ?int
    {
        $count = $record->where_lt('id', $id)
            ->order_by_desc('published')
            ->count();

        if ($count > 0) {
            return $id;
        }

        return null;
    }

    public function delete(int $id): bool
    {
        $record = ORM::for_table($this->table_name)
            ->where('id', $id)
            ->find_one();

        if ($record) {
            $record->delete();
            return true;
        }

        return false;
    }

    /**
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function tz_seconds_to_offset(int $seconds)
    {
        return ($seconds < 0 ? '-' : '+') . sprintf('%02d%02d', abs($seconds/60/60), ($seconds/60)%60);
    }

    /**
     * @author Aaron Parecki, https://aaronparecki.com
     * @copyright 2014 Aaron Parecki
     * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
     */
    public function tz_offset_to_seconds(string $offset)
    {
        if (preg_match('/([+-])(\d{2}):?(\d{2})/', $offset, $match)) {
            $sign = ($match[1] == '-' ? -1 : 1);
            return (($match[2] * 60 * 60) + ($match[3] * 60)) * $sign;
        }

        return 0;
    }

    public function get_datetime_with_offset(string $date, int $seconds): string
    {
        $offset = $this->tz_seconds_to_offset($seconds);
        $dt = new Datetime($date);
        return $dt->format('Y-m-d H:i:s') . $offset;
    }
}

