<?php
/**
 * Model for the users table
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

class User
{
    private $table_name = 'users';

    public function add(array $data): ?array 
    {
        $record = ORM::for_table($this->table_name)->create();
        $record->url = $data['url'] ?? '';
        $record->profile_slug = $data['profile_slug'] ?? '';
        $record->name = $data['name'] ?? '';
        $record->photo_url = $data['photo_url'] ?? '';
        $record->authorization_endpoint = $data['authorization_endpoint'] ?? '';
        $record->token_endpoint = $data['token_endpoint'] ?? '';
        $record->revocation_endpoint = $data['revocation_endpoint'] ?? '';
        $record->micropub_endpoint = $data['micropub_endpoint'] ?? '';
        $record->supported_visibility = $data['supported_visibility'] ?? null;
        $record->token_scope = $data['token_scope'] ?? '';
        $record->default_visibility = $data['default_visibility'] ?? 'public';
        $record->set_expr('date_created', 'NOW()');
        $record->set_expr('last_login', 'NOW()');
        $record->type = ($record->micropub_endpoint) ? 'micropub' : 'local';

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

        if (array_key_exists('name', $data)) {
            $record->name = $data['name'] ?? '';
        }

        if (array_key_exists('photo_url', $data)) {
            $record->photo_url = $data['photo_url'] ?? '';
        }

        if (array_key_exists('authorization_endpoint', $data)) {
            $record->authorization_endpoint = $data['authorization_endpoint'] ?? '';
        }

        if (array_key_exists('token_endpoint', $data)) {
            $record->token_endpoint = $data['token_endpoint'] ?? '';
        }

        if (array_key_exists('revocation_endpoint', $data)) {
            $record->revocation_endpoint = $data['revocation_endpoint'] ?? '';
        }

        if (array_key_exists('micropub_endpoint', $data)) {
            $record->micropub_endpoint = $data['micropub_endpoint'] ?? '';
        }

        if (array_key_exists('supported_visibility', $data)) {
            $record->supported_visibility = $data['supported_visibility'] ?? '';
        }

        if (array_key_exists('token_scope', $data)) {
            $record->token_scope = $data['token_scope'] ?? '';
        }

        if (array_key_exists('last_micropub_response', $data)) {
            $record->last_micropub_response = $data['last_micropub_response'] ?? '';
        }

        if (array_key_exists('default_visibility', $data)) {
            $record->default_visibility = $data['default_visibility'] ?? '';
        }

        if (array_key_exists('last_login', $data)) {
            $record->set_expr('last_login', 'NOW()');
            if (is_string($data['last_login'])) {
                $record->last_login = $data['last_login'];
            } elseif (is_null($data['last_login'])) {
                $record->last_login = null;
            }
        }

        $record->type = ($record->micropub_endpoint) ? 'micropub' : 'local';

        if ($record->save()) {
            return $this->get($id);
        }

        return null;
    }

    /**
     * Reset endpoints and last login
     */
    public function reset(int $user_id): ?array
    {
        $data = array_fill_keys([
            'authorization_endpoint',
            'token_endpoint',
            'micropub_endpoint',
            'micropub_media_endpoint',
            'token_scope',
        ], '');
        $data['last_login'] = null;

        return $this->update($user_id, $data);
    }

    public function get(int $id): ?array
    {
        $record = ORM::for_table($this->table_name)
            ->select_many_expr([
                '*',
                'display_name' => 'IF (name = "", profile_slug, name)',
                'display_photo' => 'IF (photo_url = "", "", photo_url)',
            ])
            ->where('id', $id)
            ->find_one();

        if ($record) {
            return $record->as_array();
        }

        return null;
    }

    public function findBySlug(string $slug): ?array
    {
        $record = ORM::for_table($this->table_name)
            ->select_many([
                'id',
                'type',
                'url',
                'profile_slug',
                'name',
                'photo_url',
                'last_login',
            ])
            ->where('profile_slug', $slug)
            ->find_one();

        if ($record) {
            return $record->as_array();
        }

        return null;
    }

    /**
     * Get number of users created during the specified timeframe
     */
    public function getNewCount(
        string $start_date,
        string $end_date
    ) {
        $dt_start = new DateTime($start_date);
        $dt_end = new DateTime($end_date);
        $dt_end->setTime(23, 59, 59);

        return ORM::for_table($this->table_name)
            ->where_gte('date_created', $dt_start->format('Y-m-d'))
            ->where_lte('date_created', $dt_end->format('Y-m-d'))
            ->count();
    }

    /**
     * Get number of users that signed in during the specified timeframe
     */
    public function getLoginCount(
        string $start_date,
        string $end_date
    ) {
        $dt_start = new DateTime($start_date);
        $dt_end = new DateTime($end_date);
        $dt_end->setTime(23, 59, 59);

        return ORM::for_table($this->table_name)
            ->where_gte('last_login', $dt_start->format('Y-m-d'))
            // ->where_lte('last_login', $dt_end->format('Y-m-d'))
            ->count();
    }

}

