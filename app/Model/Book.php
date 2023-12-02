<?php
/**
 * Model for the books table
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

class Book
{
    private $table_name = 'books';

    public function add(array $data): ?array
    {
        $record = ORM::for_table($this->table_name)->create();
        $record->isbn = $data['isbn'] ?? '';
        $record->entry_count = 1;
        $record->first_user_id = $data['user_id'] ?? 0;
        $record->set_expr('created', 'NOW()');
        $record->set_expr('modified', 'NOW()');

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

        if (array_key_exists('entry_count', $data)) {
            $record->entry_count = $data['entry_count'] ?? 0;
        }

        if ($record->save()) {
            return $this->get($id);
        }

        return null;
    }

    /**
     * Add an ISBN if it's new to IBC, otherwise
     * increment the entry count.
     */
    public function addOrIncrement(array $data): ?array
    {
        $isbn = $data['isbn'] ?? '';
        $record = ORM::for_table($this->table_name)
            ->where('isbn', $isbn)
            ->find_one();

        if (!$record) {
            return $this->add($data);
        }

        $data['entry_count'] = $record->entry_count + 1;

        return $this->update((int) $record->id, $data);
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
     * Get number of books created during the specified timeframe
     *
     * Note: books may have been part of a private or unlisted
     * post, so be careful when querying book information.
     * That's why current usage is only to get the total number
     * of new books.
     */
    public function getNewCount(
        string $start_date,
        string $end_date
    ): int {
        $dt_start = new DateTime($start_date);
        $dt_end = new DateTime($end_date);
        $dt_end->setTime(23, 59, 59);

        return ORM::for_table($this->table_name)
            ->where_gte('created', $dt_start->format('Y-m-d'))
            ->where_lte('created', $dt_end->format('Y-m-d'))
            ->where_null('deleted')
            ->order_by_desc('created')
            ->count();
    }
}

