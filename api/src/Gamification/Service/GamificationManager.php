<?php

/**
 * Copyright (c) 2021, MOBICOOP. All rights reserved.
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

namespace App\Gamification\Service;

use App\Action\Entity\Action;
use App\Action\Entity\Log;
use App\Action\Repository\LogRepository;
use App\Gamification\Entity\Badge;
use App\Gamification\Entity\GamificationAction;
use App\Gamification\Repository\SequenceItemRepository;
use App\User\Entity\User;
use App\Gamification\Entity\SequenceItem;
use App\Gamification\Entity\ValidationStep;
use App\Gamification\Repository\BadgeRepository;
use App\Gamification\Entity\BadgeProgression;
use App\Gamification\Entity\BadgeSummary;
use App\Gamification\Entity\RewardStep;
use App\Gamification\Entity\SequenceStatus;
use App\Gamification\Event\ValidationStepEvent;
use App\Gamification\Resource\BadgesBoard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Gamification Manager
 *
 * @author Maxime Bardot <maxime.bardot@mobicoop.org>
 */
class GamificationManager
{
    private $sequenceItemRepository;
    private $logRepository;
    private $badgeRepository;
    private $entityManager;
    private $eventDispatcher;

    public function __construct(
        SequenceItemRepository $sequenceItemRepository,
        LogRepository $logRepository,
        BadgeRepository $badgeRepository,
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->sequenceItemRepository = $sequenceItemRepository;
        $this->logRepository = $logRepository;
        $this->badgeRepository = $badgeRepository;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
    }
    
    
    /**
     * Get all the Badges of the instance
     * @param int $status  Get only the Badges of this status (default : null, every badges are returned)
     * @return Badges[]|null
     */
    public function getBadges(int $status=null): ?array
    {
        if (is_null($status)) {
            return $this->badgeRepository->findAll();
        } else {
            return $this->badgeRepository->findBy(["status"=>$status]);
        }
    }
    
    
    /**
     * When a new log entry is detected, we treat it to determine if there is something to do (i.e Gamification)
     *
     * @param Log $log          Event of the action
     * @return void
     */
    public function handleLog(Log $log)
    {
        // A new log has been recorded. We need to check if there is a gamification action to take
        $gamificationActions = $log->getAction()->getGamificationActions();
        if (is_array($gamificationActions) && count($gamificationActions)>0) {
            // This action has gamification action, we need to treat it
            foreach ($gamificationActions as $gamificationAction) {
                $this->treatGamificationAction($gamificationAction, $log->getUser());
            }
        }
    }

    /**
     * Treatment and evaluation of a GamificationAction
     *
     * @param GamificationAction $gamificationAction
     * @param User $user
     * @return void
     */
    private function treatGamificationAction(GamificationAction $gamificationAction, User $user)
    {
        // We check if this action is in a sequenceItem
        $validationSteps = [];
        $sequenceItems = $this->sequenceItemRepository->findBy(['gamificationAction'=>$gamificationAction]);
        if (is_array($sequenceItems) && count($sequenceItems)>0) {
            // This action has gamification action, we need to treat it
            /**
             * @var SequenceItem $sequenceItem
             */
            foreach ($sequenceItems as $sequenceItem) {
                $validationStep = new ValidationStep();
                $validationStep->setUser($user);
                $validationStep->setSequenceItem($sequenceItem);
                $validationStep->setValidated(true); // By default, the sequenceItem is valid

                // This related action needs to be made a minimum amount of time
                if (!is_null($sequenceItem->getMinCount()) && $sequenceItem->getMinCount()>0) {
                    $validationStep->setValidated($validationStep->isValidated() && $this->checkMinCount($gamificationAction->getAction(), $user, $sequenceItem->getMinCount()));
                }

                // Throw an event who says that a ValidationStep has been evaluated
                $validationStepEvent = new ValidationStepEvent($validationStep);
                $this->eventDispatcher->dispatch(ValidationStepEvent::NAME, $validationStepEvent);
            }
        }
    }

    /**
     * Check if the MinCount criteria is verified
     *
     * @param Action $action    The action to count
     * @param User $user        The User we count for
     * @param int $minCount     The min count to be valid
     * @return boolean  True for valid
     */
    private function checkMinCount(Action $action, User $user, int $minCount): bool
    {
        // We get in the log table all the Action $action made by this User $user
        $logs = $this->logRepository->findBy(['action'=>$action, 'user'=>$user]);
        if (is_array($logs) && count($logs)>=$minCount) {
            return true;
        }

        return false;
    }

    /**
     * Get the Badges board of a User
     *
     * @param User $user    The User
     * @return BadgesBoard
     */
    public function getBadgesBoard(User $user): BadgesBoard
    {
        $badgesBoard = new BadgesBoard();
        
        // Get all the active badges of the platform
        $activeBadges = $this->getBadges(Badge::STATUS_ACTIVE);
        $badges = [];

        /**
         * @var Badge $activeBadge
         */
        foreach ($activeBadges as $activeBadge) {
            $badgeProgression = new BadgeProgression();
            
            // Determine if the badge is already earned
            $badgeProgression->setEarned(false);
            foreach ($activeBadge->getUsers() as $userInReward) {
                if ($userInReward->getId() == $user->getId()) {
                    $badgeProgression->setEarned(true);
                    break;
                }
            }

            // Minimum data about the current badge
            $badgeSummary = new BadgeSummary();
            $badgeSummary->setBadgeId($activeBadge->getId());
            $badgeSummary->setBadgeName($activeBadge->getName());
            $badgeSummary->setBadgeTitle($activeBadge->getTitle());

            // We get the sequence and check if the current user validated it
            $sequences = [];
            foreach ($activeBadge->getSequenceItems() as $sequenceItem) {
                $sequenceStatus = new SequenceStatus();
                $sequenceStatus->setSequenceItemId($sequenceItem->getId());
                
                
                // We look into the rewardSteps previously existing for this SequenceItem
                // If there is one for the current User, we know that it has already been validated
                $sequenceStatus->setValidated(false);
                foreach ($sequenceItem->getRewardSteps() as $rewardStep) {
                    if ($rewardStep->getUser()->getId() == $user->getId()) {
                        $sequenceStatus->setValidated(true);
                        break;
                    }
                }
                $sequences[] = $sequenceStatus;
            }
            $badgeSummary->setSequences($sequences);
            $badgeProgression->setBadgeSummary($badgeSummary);


            $badges[] = $badgeProgression;
        }
        
        $badgesBoard->setBadges($badges);

        return $badgesBoard;
    }

    /**
     * Take a ValidationStep and take the necessary actions about it (RewardStep, Badge...)
     *
     * @param ValidationStep $validationStep   The ValidationStep to treat
     * @return void
     */
    public function handleValidationStep(ValidationStep $validationStep)
    {
        if ($validationStep->isValidated()) {
            // The ValidationStep has been validated

            // First we get the BadgesBoard of this User. With it, we can check if this particular step has alteady been validated
            $badgesBoard = $this->getBadgesBoard($validationStep->getUser());
            foreach ($badgesBoard->getBadges() as $badgeProgression) {
                $badgeSummary = $badgeProgression->getBadgeSummary();

                $currentSequenceValidation = []; // We will store the status of every SequenceItem
                $newValidation = false;
                foreach ($badgeSummary->getSequences() as $sequenceStatus) {

                    // We found the right sequence
                    if ($sequenceStatus->getSequenceItemId() == $validationStep->getSequenceItem()->getId()) {

                        // If it's a new validation, We store it be inserting a line in RewardStep for the User
                        if (!$sequenceStatus->isValidated()) {
                            $newValidation = true;
                            $rewardStep = new RewardStep();
                            $rewardStep->setUser($validationStep->getUser());
                            $rewardStep->setSequenceItem($validationStep->getSequenceItem());
                            $this->entityManager->persist($rewardStep);

                            // We also update the current SequenceStatus to evaluate further it this is enough to earn badge
                            $sequenceStatus->setValidated(true);
                        }
                    }
                    // We store the status of the current SequenceItem. If all validated, maybe the user earned a Badge
                    $currentSequenceValidation[] = $sequenceStatus->isValidated();
                }

                if (!in_array(false, $currentSequenceValidation)) {
                    // All steps are valid !
                    if ($newValidation) {
                        // There was a new validation, a new Badge is earned !
                        // We get the badge involved and add a User owning this Badge (add a line in Reward table)
                        $badge = $this->badgeRepository->find($badgeSummary->getBadgeId());
                        $badge->addUser($validationStep->getUser());
                        $this->entityManager->persist($badge);

                        // TO DO : Maybe dispatch an Event ? It depends how we handle a new badge in front
                    }
                }
            }



            $this->entityManager->flush();
        }
    }
}