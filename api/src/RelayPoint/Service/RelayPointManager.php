<?php

/**
 * Copyright (c) 2019, MOBICOOP. All rights reserved.
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
 **************************/

namespace App\RelayPoint\Service;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\RelayPoint\Entity\RelayPoint;

/**
 * Relay point manager.
 *
 * This service contains methods related to relay point management.
 *
 * @author Sylvain Briat <sylvain.briat@mobicoop.org>
 */
class RelayPointManager
{
    private $entityManager;
    private $logger;

    /**
     * Constructor
     *
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Update a relay point
     *
     * @param RelayPoint $relayPoint
     * @return RelayPoint
     */
    public function updateRelayPoint(RelayPoint $relayPoint): RelayPoint
    {
        if (!is_null($relayPoint->getNewAddress())) {
            $relayPoint->setAddress($relayPoint->getNewAddress());
        }
        $this->entityManager->persist($relayPoint);
        $this->entityManager->flush();
        return $relayPoint;
    }
}