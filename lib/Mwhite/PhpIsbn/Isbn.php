<?php
namespace Mwhite\PhpIsbn;

/**
 * @copyright 2012 Michael White
 * @author Michael White, http://mwhite.info/
 * @license https://opensource.org/licenses/MIT MIT
 * @see https://github.com/mwhite/php-isbn
 */

class Isbn {

    /**
     * @param string $isbn  a correctly-formatted ISBN10 or 13 (no hyphens)
     * @return boolean      whether $isbn is valid
     */
    public static function validate($isbn) {
        return self::validate10($isbn) || self::validate13($isbn);
    }

    /**
     * @param string $isbn  a correctly-formatted ISBN10 (no hyphens)
     * @return boolean      whether $isbn is valid
     */
    public static function validate10($isbn) {
        if (strlen($isbn) != 10 || !is_numeric(substr($isbn, 0, 9))) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            if ($isbn[$i] == 'X') {
                $sum += 10 * (10 - $i);
            } else if (is_numeric($isbn[$i])) {
                $sum += $isbn[$i] * (10 - $i);
            } else {
                return false;
            }
        }

        return $sum % 11 == 0;
    }

    /**
     * @param string $isbn  a correctly-formatted ISBN13 (no hyphens)
     * @return boolean      whether $isbn is valid
     */
    public static function validate13($isbn) {
        if (strlen($isbn) != 13 || !is_numeric($isbn)) {
            return false;
        }

        $i = $isbn;
        $sum = 3*($i[1] + $i[3] + $i[5] + $i[7] + $i[9] + $i[11])
                + $i[0] + $i[2] + $i[4] + $i[6] + $i[8] + $i[10];

        return $i[12] == (10 - $sum % 10) % 10;
    }

    /**
     * @param string $isbn   an ISBN of any sort in any format
     * @return false|string  $isbn converted to an ISBN10, or false if $isbn is
     *                       invalid or doesn't have an ISBN10 equivalent.
     */
    public static function to10($isbn, $validate=false) {
        $isbn = self::clean($isbn);

        if ($validate && !self::validate($isbn)) {
            return false;
        }

        if (strlen($isbn) == 10) {
            return $isbn;
        } else if (strlen($isbn) != 13 || substr($isbn, 0, 3) != '978') {
            return false;
        }

        $i = substr($isbn, 3);
        $sum = $i[0]*1 + $i[1]*2 + $i[2]*3 + $i[3]*4 + $i[4]*5
                       + $i[5]*6 + $i[6]*7 + $i[7]*8 + $i[8]*9;

        $check = $sum % 11;
        if ($check == 10) {
            $check = "X";
        }

        return substr($isbn, 3, 9) . $check;

    }

    /**
     * @param string $isbn   an ISBN of any sort in any format
     * @return false|string  $isbn converted to an ISBN13, or false if $isbn is
     *                       invalid
     */
    public static function to13($isbn, $validate=false) {
        $isbn = self::clean($isbn);

        if ($validate && !self::validate($isbn)) {
            return false;
        }

        if (strlen($isbn) == 13) {
            return $isbn;
        } else if (strlen($isbn) != 10) {
            return false;
        }

        $i = "978" . substr($isbn, 0, -1);
        $sum = 3*($i[1] + $i[3] + $i[5] + $i[7] + $i[9] + $i[11])
                + $i[0] + $i[2] + $i[4] + $i[6] + $i[8] + $i[10];

        $check = $sum % 10;
        if ($check != 0) {
            $check = 10 - $check;
        }

        return $i . $check;
    }

    /**
     * @param string $isbn  an ISBN in any format, including whitespace, hyphens, etc.
     * @return string       $isbn with all characters removed except numbers and 'X'
     */
    public static function clean($isbn) {
        return preg_replace("/[^0-9X]+/", '', $isbn);
    }

}

