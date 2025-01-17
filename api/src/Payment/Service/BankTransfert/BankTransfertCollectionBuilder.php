<?php
/**
 * Copyright (c) 2022, MOBICOOP. All rights reserved.
 * This project is dual licensed under AGPL and proprietary licence.
 ***************************
 *    This program is free software: you can redistribute it and/or modify
 *    it under the terms of the GNU Affero General Public License as
 *    published by the Free Software Foundation, either version 3 of the
 *    License, or (at your option) any later version.
 *
 *    This program is distributed in the hope that it will be useful,
 *    but WITHOUT ANY WARRANTY; without even the implied warranty of
 *    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *    GNU Affero General Public License for more details.
 *
 *    You should have received a copy of the GNU Affero General Public License
 *    along with this program.  If not, see <gnu.org/licenses>.
 ***************************
 *    Licence MOBICOOP described in the file
 *    LICENSE
 */

namespace App\Payment\Service\BankTransfert;

use App\Payment\Exception\BankTransfertException;
use Psr\Log\LoggerInterface;

/**
 * Bank Transfert Builder.
 *
 * @author Maxime Bardot <maxime.bardot@mobicoop.org>
 */
class BankTransfertCollectionBuilder
{
    public const CSV_DELIMITER = ';';

    /**
     * @var string
     */
    private $_filepath;

    /**
     * @var array
     */
    private $_bankTransferts;

    /**
     * @var string
     */
    private $_batchId;

    /**
     * @var BankTransfertBuilder
     */
    private $_bankTransfertBuilder;

    private $_logger;

    public function __construct(BankTransfertBuilder $bankTransfertBuilder, LoggerInterface $logger)
    {
        $this->_bankTransferts = [];
        $this->_bankTransfertBuilder = $bankTransfertBuilder;
        $this->_logger = $logger;
    }

    public function setFilePath(string $filepath): self
    {
        $this->_filepath = $filepath;

        return $this;
    }

    public function getBankTransferts(): array
    {
        return $this->_bankTransferts;
    }

    public function getBatchId(): string
    {
        return $this->_batchId;
    }

    public function build()
    {
        try {
            $file = fopen($this->_filepath, 'r');
        } catch (\Exception $e) {
            throw new BankTransfertException(BankTransfertException::ERROR_OPENING_FILE.' '.$this->_filepath);
        }

        $this->_batchId = $this->_generateUuid();
        $this->_logger->info('Starting BatchId : '.$this->_batchId);
        while (!feof($file)) {
            $line = fgetcsv($file, 0, self::CSV_DELIMITER);
            if ($line) {
                $this->_bankTransfertBuilder->setData($line);
                if (!is_null($bankTransfert = $this->_bankTransfertBuilder->build($this->_batchId))) {
                    $this->_bankTransferts[] = $bankTransfert;
                }
            }
        }

        fclose($file);
    }

    private function _generateUuid()
    {
        // Generate a random string of bytes
        $bytes = openssl_random_pseudo_bytes(16);

        // Convert the bytes to a hexadecimal string
        $hex = bin2hex($bytes);

        // Format the hexadecimal string as a UUID
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
