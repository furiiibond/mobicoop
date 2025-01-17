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

namespace App\Scammer\Admin\Service;

use App\Carpool\Repository\AskRepository;
use App\Scammer\Entity\Scammer;
use App\Scammer\Event\ScammerAddedEvent;
use App\User\Admin\Service\UserManager;
use App\User\Entity\User;
use App\User\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Scammer manager service for administration.
 *
 * @author Remi Wortemann <remi.wortemann@mobicoop.org>
 */
class ScammerManager
{
    private $entityManager;
    private $eventDispatcher;
    private $userManager;
    private $userRepository;
    private $askRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $dispatcher,
        UserRepository $userRepository,
        UserManager $userManager,
        AskRepository $askRepository
    ) {
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $dispatcher;
        $this->userRepository = $userRepository;
        $this->userManager = $userManager;
        $this->askRepository = $askRepository;
    }

    /**
     * Add a scammer.
     *
     * @param Scammer $scammer The scammer to add
     *
     * @return Scammer The scammer added
     */
    public function addScammer(Scammer $scammer, User $user)
    {
        $scammer->setUser($user);
        $this->entityManager->persist($scammer);
        $this->entityManager->flush();

        $scammerReported = $this->userRepository->findOneBy(['email' => $scammer->getEmail()]);

        $scammerVictims = $this->getScammerVictims($scammerReported);

        if (count($scammerVictims) > 0) {
            //  we dispatch the event associated
            $event = new ScammerAddedEvent($scammer, $scammerVictims);
            $this->eventDispatcher->dispatch($event, ScammerAddedEvent::NAME);
        }
        // we delete the user reported
        $this->userManager->deleteUser($scammerReported);

        return $scammer;
    }

    /**
     * Get all potential victims of the scammer.
     */
    public function getScammerVictims(User $scammerReported)
    {
        return $this->askRepository->getUsersIdsInContactWithCurrentUser($scammerReported);
    }
}
