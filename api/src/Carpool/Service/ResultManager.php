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

namespace App\Carpool\Service;

use App\Carpool\Entity\Criteria;
use App\Carpool\Entity\Matching;
use App\Carpool\Entity\Proposal;
use App\Carpool\Entity\Result;
use App\Carpool\Entity\ResultItem;
use App\Carpool\Entity\ResultRole;
use App\Service\FormatDataManager;

/**
 * Result manager service.
 * Used to create user-friendly results from the matching system.
 *
 * @author Sylvain Briat <sylvain.briat@mobicoop.org>
 */
class ResultManager
{
    private $formatDataManager;
    private $params;

    /**
     * Constructor.
     *
     * @param FormatDataManager $proposalMatcher
     * @param array $params
     */
    public function __construct(FormatDataManager $formatDataManager)
    {
        $this->formatDataManager = $formatDataManager;
    }

    // set the params
    public function setParams(array $params)
    {
        $this->params = $params;
    }

    /**
     * Create "user-friendly" results from the matchings of a proposal
     *
     * @param Proposal $proposal    The proposal with its matchings
     * @return array                The array of results
     */
    public function createResults(Proposal $proposal)
    {
        $results = [];
        // we group the matchings by matching proposalId to merge potential driver and/or passenger candidates
        $matchings = [];
        // we search the matchings as an offer
        foreach ($proposal->getMatchingRequests() as $request) {
            $matchings[$request->getProposalRequest()->getId()]['request'] = $request;
        }
        // we search the matchings as a request
        foreach ($proposal->getMatchingOffers() as $offer) {
            $matchings[$offer->getProposalOffer()->getId()]['offer'] = $offer;
        }
        // we iterate through the matchings to create the results
        foreach ($matchings as $proposalId => $matching) {
            $result = new Result();

            /************/
            /*  REQUEST */
            /************/
            if (isset($matching['request'])) {
                // the carpooler can be passenger
                if (is_null($result->getFrequency())) {
                    $result->setFrequency($matching['request']->getCriteria()->getFrequency());
                }
                if (is_null($result->getFrequencyResult())) {
                    $result->setFrequencyResult($matching['request']->getProposalRequest()->getCriteria()->getFrequency());
                }
                if (is_null($result->getCarpooler())) {
                    $result->setCarpooler($matching['request']->getProposalRequest()->getUser());
                }
                if (is_null($result->getComment()) && !is_null($matching['request']->getProposalRequest()->getComment())) {
                    $result->setComment($matching['request']->getProposalRequest()->getComment());
                }
                $resultDriver = new ResultRole();
                // outward
                $outward = new ResultItem();
                // we set the proposalId
                $outward->setProposalId($proposalId);
                if ($matching['request']->getId() !== Matching::DEFAULT_ID) {
                    $outward->setMatchingId($matching['request']->getId());
                }
                if ($proposal->getCriteria()->getFrequency() == Criteria::FREQUENCY_PUNCTUAL) {
                    // the search/ad proposal is punctual
                    // we have to calculate the date and time of the carpool
                    // date :
                    // - if the matching proposal is also punctual, it's the date of the matching proposal (as the date of the matching proposal could be the same or after the date of the search/ad)
                    // - if the matching proposal is regular, it's the date of the search/ad (as the matching proposal "matches", it means that the date is valid => the date is in the range of the regular matching proposal)
                    if ($matching['request']->getProposalRequest()->getCriteria()->getFrequency() == Criteria::FREQUENCY_PUNCTUAL) {
                        $outward->setDate($matching['request']->getProposalRequest()->getCriteria()->getFromDate());
                    } else {
                        $outward->setDate($proposal->getCriteria()->getFromDate());
                    }
                    // time
                    // the carpooler is passenger, the proposal owner is driver : we use his time if it's set
                    if ($proposal->getCriteria()->getFromTime()) {
                        $outward->setTime($proposal->getCriteria()->getFromTime());
                    } else {
                        // the time is not set, it must be the matching results of a search (and not an ad)
                        // we have to calculate the starting time so that the driver will get the carpooler on the carpooler time
                        // we init the time to the one of the carpooler
                        if ($matching['request']->getProposalRequest()->getCriteria()->getFrequency() == Criteria::FREQUENCY_PUNCTUAL) {
                            // the carpooler proposal is punctual, we take the fromTime
                            $fromTime = clone $matching['request']->getProposalRequest()->getCriteria()->getFromTime();
                        } else {
                            // the carpooler proposal is regular, we have to take the search/ad day's time
                            switch ($proposal->getCriteria()->getFromDate()->format('w')) {
                                case 0: {
                                    $fromTime = clone $matching['request']->getProposalRequest()->getCriteria()->getSunTime();
                                    break;
                                }
                                case 1: {
                                    $fromTime = clone $matching['request']->getProposalRequest()->getCriteria()->getMonTime();
                                    break;
                                }
                                case 2: {
                                    $fromTime = clone $matching['request']->getProposalRequest()->getCriteria()->getTueTime();
                                    break;
                                }
                                case 3: {
                                    $fromTime = clone $matching['request']->getProposalRequest()->getCriteria()->getWedTime();
                                    break;
                                }
                                case 4: {
                                    $fromTime = clone $matching['request']->getProposalRequest()->getCriteria()->getThuTime();
                                    break;
                                }
                                case 5: {
                                    $fromTime = clone $matching['request']->getProposalRequest()->getCriteria()->getFriTime();
                                    break;
                                }
                                case 6: {
                                    $fromTime = clone $matching['request']->getProposalRequest()->getCriteria()->getSatTime();
                                    break;
                                }
                            }
                        }
                        // we search the pickup duration
                        $filters = $matching['request']->getFilters();
                        $pickupDuration = null;
                        foreach ($filters['route'] as $value) {
                            if ($value['candidate'] == 2 && $value['position'] == 0) {
                                $pickupDuration = (int)round($value['duration']);
                                break;
                            }
                        }
                        if ($pickupDuration) {
                            $fromTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                        }
                        $outward->setTime($fromTime);
                    }
                } else {
                    // the search or ad is regular => no date
                    // we have to find common days (if it's a search the common days should be the carpooler days)
                    // we check if pickup times have been calculated already
                    if (isset($matching['request']->getFilters()['pickup'])) {
                        // we have pickup times, it must be the matching results of an ad (and not a search)
                        // the carpooler is passenger, the proposal owner is driver : we use his time as it must be set
                        // we use the times even if we don't use them, maybe we'll need them in the future
                        // we set the global time for each day, we will erase it if we discover that all days have not the same time
                        // this way we are sure that if all days have the same time, the global time will be set and ok
                        if (isset($matching['request']->getFilters()['pickup']['monMinPickupTime']) && isset($matching['request']->getFilters()['pickup']['monMaxPickupTime'])) {
                            $outward->setMonCheck(true);
                            $outward->setMonTime($proposal->getCriteria()->getMonTime());
                            $outward->setTime($proposal->getCriteria()->getMonTime());
                        }
                        if (isset($matching['request']->getFilters()['pickup']['tueMinPickupTime']) && isset($matching['request']->getFilters()['pickup']['tueMaxPickupTime'])) {
                            $outward->setTueCheck(true);
                            $outward->setTueTime($proposal->getCriteria()->getTueTime());
                            $outward->setTime($proposal->getCriteria()->getTueTime());
                        }
                        if (isset($matching['request']->getFilters()['pickup']['wedMinPickupTime']) && isset($matching['request']->getFilters()['pickup']['wedMaxPickupTime'])) {
                            $outward->setWedCheck(true);
                            $outward->setWedTime($proposal->getCriteria()->getWedTime());
                            $outward->setTime($proposal->getCriteria()->getWedTime());
                        }
                        if (isset($matching['request']->getFilters()['pickup']['thuMinPickupTime']) && isset($matching['request']->getFilters()['pickup']['thuMaxPickupTime'])) {
                            $outward->setThuCheck(true);
                            $outward->setThuTime($proposal->getCriteria()->getThuTime());
                            $outward->setTime($proposal->getCriteria()->getThuTime());
                        }
                        if (isset($matching['request']->getFilters()['pickup']['friMinPickupTime']) && isset($matching['request']->getFilters()['pickup']['friMaxPickupTime'])) {
                            $outward->setFriCheck(true);
                            $outward->setFriTime($proposal->getCriteria()->getFriTime());
                            $outward->setTime($proposal->getCriteria()->getFriTime());
                        }
                        if (isset($matching['request']->getFilters()['pickup']['satMinPickupTime']) && isset($matching['request']->getFilters()['pickup']['satMaxPickupTime'])) {
                            $outward->setSatCheck(true);
                            $outward->setSatTime($proposal->getCriteria()->getSatTime());
                            $outward->setTime($proposal->getCriteria()->getSatTime());
                        }
                        if (isset($matching['request']->getFilters()['pickup']['sunMinPickupTime']) && isset($matching['request']->getFilters()['pickup']['sunMaxPickupTime'])) {
                            $outward->setSunCheck(true);
                            $outward->setSunTime($proposal->getCriteria()->getSunTime());
                            $outward->setTime($proposal->getCriteria()->getSunTime());
                        }
                    } else {
                        // no pick up times, it must be the matching results of a search (and not an ad)
                        // the days are the carpooler days
                        $outward->setMonCheck($matching['request']->getProposalRequest()->getCriteria()->isMonCheck());
                        $outward->setTueCheck($matching['request']->getProposalRequest()->getCriteria()->isTueCheck());
                        $outward->setWedCheck($matching['request']->getProposalRequest()->getCriteria()->isWedCheck());
                        $outward->setThuCheck($matching['request']->getProposalRequest()->getCriteria()->isThuCheck());
                        $outward->setFriCheck($matching['request']->getProposalRequest()->getCriteria()->isFriCheck());
                        $outward->setSatCheck($matching['request']->getProposalRequest()->getCriteria()->isSatCheck());
                        $outward->setSunCheck($matching['request']->getProposalRequest()->getCriteria()->isSunCheck());
                        // we calculate the starting time so that the driver will get the carpooler on the carpooler time
                        // even if we don't use them, maybe we'll need them in the future
                        $filters = $matching['request']->getFilters();
                        $pickupDuration = null;
                        foreach ($filters['route'] as $value) {
                            if ($value['candidate'] == 2 && $value['position'] == 0) {
                                $pickupDuration = (int)round($value['duration']);
                                break;
                            }
                        }
                        // we init the time to the one of the carpooler
                        if ($matching['request']->getProposalRequest()->getCriteria()->isMonCheck()) {
                            $monTime = clone $matching['request']->getProposalRequest()->getCriteria()->getMonTime();
                            if ($pickupDuration) {
                                $monTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setMonTime($monTime);
                            $outward->setTime($monTime);
                        }
                        if ($matching['request']->getProposalRequest()->getCriteria()->isTueCheck()) {
                            $tueTime = clone $matching['request']->getProposalRequest()->getCriteria()->getTueTime();
                            if ($pickupDuration) {
                                $tueTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setTueTime($tueTime);
                            $outward->setTime($tueTime);
                        }
                        if ($matching['request']->getProposalRequest()->getCriteria()->isWedCheck()) {
                            $wedTime = clone $matching['request']->getProposalRequest()->getCriteria()->getWedTime();
                            if ($pickupDuration) {
                                $wedTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setWedTime($wedTime);
                            $outward->setTime($wedTime);
                        }
                        if ($matching['request']->getProposalRequest()->getCriteria()->isThuCheck()) {
                            $thuTime = clone $matching['request']->getProposalRequest()->getCriteria()->getThuTime();
                            if ($pickupDuration) {
                                $thuTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setThuTime($thuTime);
                            $outward->setTime($thuTime);
                        }
                        if ($matching['request']->getProposalRequest()->getCriteria()->isFriCheck()) {
                            $friTime = clone $matching['request']->getProposalRequest()->getCriteria()->getFriTime();
                            if ($pickupDuration) {
                                $friTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setFriTime($friTime);
                            $outward->setTime($friTime);
                        }
                        if ($matching['request']->getProposalRequest()->getCriteria()->isSatCheck()) {
                            $satTime = clone $matching['request']->getProposalRequest()->getCriteria()->getSatTime();
                            if ($pickupDuration) {
                                $satTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setSatTime($satTime);
                            $outward->setTime($satTime);
                        }
                        if ($matching['request']->getProposalRequest()->getCriteria()->isSunCheck()) {
                            $sunTime = clone $matching['request']->getProposalRequest()->getCriteria()->getSunTime();
                            if ($pickupDuration) {
                                $sunTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setSunTime($sunTime);
                            $outward->setTime($sunTime);
                        }
                    }
                    $outward->setMultipleTimes();
                    if ($outward->hasMultipleTimes()) {
                        $outward->setTime(null);
                    }
                    // fromDate is the max between the search date and the fromDate of the matching proposal
                    $outward->setFromDate(max(
                        $matching['request']->getProposalRequest()->getCriteria()->getFromDate(),
                        $proposal->getCriteria()->getFromDate()
                    ));
                    $outward->setToDate($matching['request']->getProposalRequest()->getCriteria()->getToDate());
                }
                // waypoints of the outward
                $waypoints = [];
                $time = $outward->getTime() ? clone $outward->getTime() : null;
                // we will have to compute the number of steps fo reach candidate
                $steps = [
                    'requester' => 0,
                    'carpooler' => 0
                ];
                // first pass to get the maximum position fo each candidate
                foreach ($matching['request']->getFilters()['route'] as $key=>$waypoint) {
                    if ($waypoint['candidate'] == 1 && (int)$waypoint['position']>$steps['requester']) {
                        $steps['requester'] = (int)$waypoint['position'];
                    } elseif ($waypoint['candidate'] == 2 && (int)$waypoint['position']>$steps['carpooler']) {
                        $steps['carpooler'] = (int)$waypoint['position'];
                    }
                }
                // second pass to fill the waypoints array
                foreach ($matching['request']->getFilters()['route'] as $key=>$waypoint) {
                    $curTime = null;
                    if ($time) {
                        $curTime = clone $time;
                    }
                    if ($curTime) {
                        $curTime->add(new \DateInterval('PT' . (int)round($waypoint['duration']) . 'S'));
                    }
                    $waypoints[$key] = [
                        'id' => $key,
                        'person' => $waypoint['candidate'] == 1 ? 'requester' : 'carpooler',
                        'role' => $waypoint['candidate'] == 1 ? 'driver' : 'passenger',
                        'time' =>  $curTime,
                        'address' => $waypoint['address'],
                        'type' => $waypoint['position'] == '0' ? 'origin' :
                            (
                                ($waypoint['candidate'] == 1) ? ((int)$waypoint['position'] == $steps['requester'] ? 'destination' : 'step') :
                                ((int)$waypoint['position'] == $steps['carpooler'] ? 'destination' : 'step')
                            )
                    ];
                    // origin and destination guess
                    if ($waypoint['candidate'] == 2 && $waypoint['position'] == '0') {
                        $outward->setOrigin($waypoint['address']);
                        $outward->setOriginPassenger($waypoint['address']);
                    } elseif ($waypoint['candidate'] == 2 && (int)$waypoint['position'] == $steps['carpooler']) {
                        $outward->setDestination($waypoint['address']);
                        $outward->setDestinationPassenger($waypoint['address']);
                    } elseif ($waypoint['candidate'] == 1 && $waypoint['position'] == '0') {
                        $outward->setOriginDriver($waypoint['address']);
                    } elseif ($waypoint['candidate'] == 1 && (int)$waypoint['position'] == $steps['requester']) {
                        $outward->setDestinationDriver($waypoint['address']);
                    }
                }
                $outward->setWaypoints($waypoints);
                
                // statistics
                $outward->setOriginalDistance($matching['request']->getFilters()['originalDistance']);
                $outward->setAcceptedDetourDistance($matching['request']->getFilters()['acceptedDetourDistance']);
                $outward->setNewDistance($matching['request']->getFilters()['newDistance']);
                $outward->setDetourDistance($matching['request']->getFilters()['detourDistance']);
                $outward->setDetourDistancePercent($matching['request']->getFilters()['detourDistancePercent']);
                $outward->setOriginalDuration($matching['request']->getFilters()['originalDuration']);
                $outward->setAcceptedDetourDuration($matching['request']->getFilters()['acceptedDetourDuration']);
                $outward->setNewDuration($matching['request']->getFilters()['newDuration']);
                $outward->setDetourDuration($matching['request']->getFilters()['detourDuration']);
                $outward->setDetourDurationPercent($matching['request']->getFilters()['detourDurationPercent']);
                $outward->setCommonDistance($matching['request']->getFilters()['commonDistance']);

                // prices

                // we set the prices of the driver (the requester)
                // if the requester price per km is set we use it
                if ($proposal->getCriteria()->getPriceKm()) {
                    $outward->setDriverPriceKm($proposal->getCriteria()->getPriceKm());
                } else {
                    // otherwise we use the common price
                    $outward->setDriverPriceKm($this->params['defaultPriceKm']);
                }
                // if the requester price is set we use it
                if ($proposal->getCriteria()->getPrice()) {
                    $outward->setDriverOriginalPrice($proposal->getCriteria()->getPrice());
                } else {
                    // otherwise we use the common price
                    $outward->setDriverOriginalPrice((string)((int)$matching['request']->getFilters()['originalDistance']*(float)$outward->getDriverPriceKm()/1000));
                }
                $outward->setDriverOriginalRoundedPrice((string)$this->formatDataManager->roundPrice((float)$outward->getDriverOriginalPrice(), $proposal->getCriteria()->getFrequency()));
                
                // we set the prices of the passenger (the carpooler)
                $outward->setPassengerPriceKm($matching['request']->getProposalRequest()->getCriteria()->getPriceKm());
                $outward->setPassengerOriginalPrice($matching['request']->getProposalRequest()->getCriteria()->getPrice());
                $outward->setPassengerOriginalRoundedPrice($matching['request']->getProposalRequest()->getCriteria()->getRoundedPrice());

                // the computed price is the price to be paid by the passenger
                // it's ((common distance + detour distance) * driver price by km)
                $outward->setComputedPrice((string)(((int)$matching['request']->getFilters()['commonDistance']+(int)$matching['request']->getFilters()['detourDistance'])*(float)$outward->getDriverPriceKm()/1000));
                $outward->setComputedRoundedPrice((string)$this->formatDataManager->roundPrice((float)$outward->getComputedPrice(), $proposal->getCriteria()->getFrequency()));
                $resultDriver->setOutward($outward);
                
                // return trip, only for regular trip for now
                if ($matching['request']->getProposalRequest()->getProposalLinked() && $proposal->getCriteria()->getFrequency() == Criteria::FREQUENCY_REGULAR && $matching['request']->getProposalRequest()->getCriteria()->getFrequency() == Criteria::FREQUENCY_REGULAR) {
                    $requestProposalLinked = $matching['request']->getProposalRequest()->getProposalLinked();
                    $offerProposalLinked = $matching['request']->getProposalOffer()->getProposalLinked();
                    $matchingRelated = $matching['request']->getMatchingRelated();
                    
                    // /!\ we only treat the return days /!\
                    $return = new ResultItem();
                    // we use the carpooler days as we don't have a matching here
                    $return->setMonCheck($requestProposalLinked->getCriteria()->isMonCheck());
                    $return->setTueCheck($requestProposalLinked->getCriteria()->isTueCheck());
                    $return->setWedCheck($requestProposalLinked->getCriteria()->isWedCheck());
                    $return->setThuCheck($requestProposalLinked->getCriteria()->isThuCheck());
                    $return->setFriCheck($requestProposalLinked->getCriteria()->isFriCheck());
                    $return->setSatCheck($requestProposalLinked->getCriteria()->isSatCheck());
                    $return->setSunCheck($requestProposalLinked->getCriteria()->isSunCheck());
                    $return->setFromDate($requestProposalLinked->getCriteria()->getFromDate());
                    $return->setToDate($requestProposalLinked->getCriteria()->getToDate());

                    if ($matchingRelated) {
                        // we calculate the starting time so that the driver will get the carpooler on the carpooler time
                        // even if we don't use them, maybe we'll need them in the future
                        $filters = $matchingRelated->getFilters();
                        $pickupDuration = null;
                        foreach ($filters['route'] as $value) {
                            if ($value['candidate'] == 2 && $value['position'] == 0) {
                                $pickupDuration = (int)round($value['duration']);
                                break;
                            }
                        }
                        // we init the time to the one of the carpooler
                        if ($requestProposalLinked->getCriteria()->isMonCheck()) {
                            $monTime = clone $requestProposalLinked->getCriteria()->getMonTime();
                            if ($pickupDuration) {
                                $monTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setMonTime($monTime);
                            $return->setTime($monTime);
                        }
                        if ($requestProposalLinked->getCriteria()->isTueCheck()) {
                            $tueTime = clone $requestProposalLinked->getCriteria()->getTueTime();
                            if ($pickupDuration) {
                                $tueTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setTueTime($tueTime);
                            $return->setTime($tueTime);
                        }
                        if ($requestProposalLinked->getCriteria()->isWedCheck()) {
                            $wedTime = clone $requestProposalLinked->getCriteria()->getWedTime();
                            if ($pickupDuration) {
                                $wedTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setWedTime($wedTime);
                            $return->setTime($wedTime);
                        }
                        if ($requestProposalLinked->getCriteria()->isThuCheck()) {
                            $thuTime = clone $requestProposalLinked->getCriteria()->getThuTime();
                            if ($pickupDuration) {
                                $thuTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setThuTime($thuTime);
                            $return->setTime($thuTime);
                        }
                        if ($requestProposalLinked->getCriteria()->isFriCheck()) {
                            $friTime = clone $requestProposalLinked->getCriteria()->getFriTime();
                            if ($pickupDuration) {
                                $friTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setFriTime($friTime);
                            $return->setTime($friTime);
                        }
                        if ($requestProposalLinked->getCriteria()->isSatCheck()) {
                            $satTime = clone $requestProposalLinked->getCriteria()->getSatTime();
                            if ($pickupDuration) {
                                $satTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setSatTime($satTime);
                            $return->setTime($satTime);
                        }
                        if ($requestProposalLinked->getCriteria()->isSunCheck()) {
                            $sunTime = clone $requestProposalLinked->getCriteria()->getSunTime();
                            if ($pickupDuration) {
                                $sunTime->sub(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setSunTime($sunTime);
                            $return->setTime($sunTime);
                        }
                        // fromDate is the max between the search date and the fromDate of the matching proposal
                        $return->setFromDate(max(
                            $matchingRelated->getProposalRequest()->getCriteria()->getFromDate(),
                            $proposal->getCriteria()->getFromDate()
                        ));
                        $return->setToDate($matchingRelated->getProposalRequest()->getCriteria()->getToDate());
                    
                        // waypoints of the return
                        $waypoints = [];
                        $time = $return->getTime() ? clone $return->getTime() : null;
                        // we will have to compute the number of steps for each candidate
                        $steps = [
                            'requester' => 0,
                            'carpooler' => 0
                        ];
                        // first pass to get the maximum position for each candidate
                        foreach ($matchingRelated->getFilters()['route'] as $key=>$waypoint) {
                            if ($waypoint['candidate'] == 1 && (int)$waypoint['position']>$steps['requester']) {
                                $steps['requester'] = (int)$waypoint['position'];
                            } elseif ($waypoint['candidate'] == 2 && (int)$waypoint['position']>$steps['carpooler']) {
                                $steps['carpooler'] = (int)$waypoint['position'];
                            }
                        }
                        // second pass to fill the waypoints array
                        foreach ($matchingRelated->getFilters()['route'] as $key=>$waypoint) {
                            $curTime = null;
                            if ($time) {
                                $curTime = clone $time;
                            }
                            if ($curTime) {
                                $curTime->add(new \DateInterval('PT' . (int)round($waypoint['duration']) . 'S'));
                            }
                            $waypoints[$key] = [
                                'id' => $key,
                                'person' => $waypoint['candidate'] == 1 ? 'requester' : 'carpooler',
                                'role' => $waypoint['candidate'] == 1 ? 'driver' : 'passenger',
                                'time' =>  $curTime,
                                'address' => $waypoint['address'],
                                'type' => $waypoint['position'] == '0' ? 'origin' :
                                    (
                                        ($waypoint['candidate'] == 1) ? ((int)$waypoint['position'] == $steps['requester'] ? 'destination' : 'step') :
                                        ((int)$waypoint['position'] == $steps['carpooler'] ? 'destination' : 'step')
                                    )
                            ];
                            // origin and destination guess
                            if ($waypoint['candidate'] == 2 && $waypoint['position'] == '0') {
                                $return->setOrigin($waypoint['address']);
                                $return->setOriginPassenger($waypoint['address']);
                            } elseif ($waypoint['candidate'] == 2 && (int)$waypoint['position'] == $steps['carpooler']) {
                                $return->setDestination($waypoint['address']);
                                $return->setDestinationPassenger($waypoint['address']);
                            } elseif ($waypoint['candidate'] == 1 && $waypoint['position'] == '0') {
                                $return->setOriginDriver($waypoint['address']);
                            } elseif ($waypoint['candidate'] == 1 && (int)$waypoint['position'] == $steps['requester']) {
                                $return->setDestinationDriver($waypoint['address']);
                            }
                        }
                        $return->setWaypoints($waypoints);
                        
                        // statistics
                        if ($matchingRelated->getFilters()['originalDistance']) {
                            $return->setOriginalDistance($matchingRelated->getFilters()['originalDistance']);
                        }
                        if ($matchingRelated->getFilters()['acceptedDetourDistance']) {
                            $return->setAcceptedDetourDistance($matchingRelated->getFilters()['acceptedDetourDistance']);
                        }
                        if ($matchingRelated->getFilters()['newDistance']) {
                            $return->setNewDistance($matchingRelated->getFilters()['newDistance']);
                        }
                        if ($matchingRelated->getFilters()['detourDistance']) {
                            $return->setDetourDistance($matchingRelated->getFilters()['detourDistance']);
                        }
                        if ($matchingRelated->getFilters()['detourDistancePercent']) {
                            $return->setDetourDistancePercent($matchingRelated->getFilters()['detourDistancePercent']);
                        }
                        if ($matchingRelated->getFilters()['originalDuration']) {
                            $return->setOriginalDuration($matchingRelated->getFilters()['originalDuration']);
                        }
                        if ($matchingRelated->getFilters()['acceptedDetourDuration']) {
                            $return->setAcceptedDetourDuration($matchingRelated->getFilters()['acceptedDetourDuration']);
                        }
                        if ($matchingRelated->getFilters()['newDuration']) {
                            $return->setNewDuration($matchingRelated->getFilters()['newDuration']);
                        }
                        if ($matchingRelated->getFilters()['detourDuration']) {
                            $return->setDetourDuration($matchingRelated->getFilters()['detourDuration']);
                        }
                        if ($matchingRelated->getFilters()['detourDurationPercent']) {
                            $return->setDetourDurationPercent($matchingRelated->getFilters()['detourDurationPercent']);
                        }
                        if ($matchingRelated->getFilters()['commonDistance']) {
                            $return->setCommonDistance($matchingRelated->getFilters()['commonDistance']);
                        }
                        
                        // prices

                        // we set the prices of the driver (the requester)
                        // if the requester price per km is set we use it
                        if ($offerProposalLinked && $offerProposalLinked->getCriteria()->getPriceKm()) {
                            $return->setDriverPriceKm($offerProposalLinked->getCriteria()->getPriceKm());
                        } else {
                            // otherwise we use the common price
                            $return->setDriverPriceKm($this->params['defaultPriceKm']);
                        }
                        // if the requester price is set we use it
                        if ($offerProposalLinked && $offerProposalLinked->getCriteria()->getPrice()) {
                            $return->setDriverOriginalPrice($offerProposalLinked->getCriteria()->getPrice());
                        } else {
                            // otherwise we use the common price
                            $return->setDriverOriginalPrice((string)((int)$matchingRelated->getFilters()['originalDistance']*(float)$return->getDriverPriceKm()/1000));
                        }
                        $return->setDriverOriginalRoundedPrice((string)$this->formatDataManager->roundPrice((float)$return->getDriverOriginalPrice(), $proposal->getCriteria()->getFrequency()));
                        
                        // we set the prices of the passenger (the carpooler)
                        $return->setPassengerPriceKm($requestProposalLinked->getCriteria()->getPriceKm());
                        $return->setPassengerOriginalPrice($requestProposalLinked->getCriteria()->getPrice());
                        $return->setPassengerOriginalRoundedPrice($requestProposalLinked->getCriteria()->getRoundedPrice());

                        // the computed price is the price to be paid by the passenger
                        // it's ((common distance + detour distance) * driver price by km)
                        $return->setComputedPrice((string)(((int)$matchingRelated->getFilters()['commonDistance']+(int)$matchingRelated->getFilters()['detourDistance'])*(float)$return->getDriverPriceKm()/1000));
                        $return->setComputedRoundedPrice((string)$this->formatDataManager->roundPrice((float)$return->getComputedPrice(), $proposal->getCriteria()->getFrequency()));
                    }
                    $return->setMultipleTimes();
                    if ($return->hasMultipleTimes()) {
                        $return->setTime(null);
                    }

                    $resultDriver->setReturn($return);
                }

                // seats
                $resultDriver->setSeats($proposal->getCriteria()->getSeats() ? $proposal->getCriteria()->getSeats() : 1);
                $result->setResultDriver($resultDriver);
            }

            /************/
            /*  OFFER   */
            /************/
            if (isset($matching['offer'])) {
                // the carpooler can be driver
                if (is_null($result->getFrequency())) {
                    $result->setFrequency($matching['offer']->getCriteria()->getFrequency());
                }
                if (is_null($result->getFrequencyResult())) {
                    $result->setFrequencyResult($matching['offer']->getProposalOffer()->getCriteria()->getFrequency());
                }
                if (is_null($result->getCarpooler())) {
                    $result->setCarpooler($matching['offer']->getProposalOffer()->getUser());
                }
                if (is_null($result->getComment()) && !is_null($matching['offer']->getProposalOffer()->getComment())) {
                    $result->setComment($matching['offer']->getProposalOffer()->getComment());
                }
                $resultPassenger = new ResultRole();

                // outward
                $outward = new ResultItem();
                // we set the proposalId
                $outward->setProposalId($proposalId);
                if ($matching['offer']->getId() !== Matching::DEFAULT_ID) {
                    $outward->setMatchingId($matching['offer']->getId());
                }
                $driverFromTime = null;
                if ($proposal->getCriteria()->getFrequency() == Criteria::FREQUENCY_PUNCTUAL) {
                    // the search/ad proposal is punctual
                    // we have to calculate the date and time of the carpool
                    // date :
                    // - if the matching proposal is also punctual, it's the date of the matching proposal (as the date of the matching proposal could be the same or after the date of the search/ad)
                    // - if the matching proposal is regular, it's the date of the search/ad (as the matching proposal "matches", it means that the date is valid => the date is in the range of the regular matching proposal)
                    if ($matching['offer']->getProposalOffer()->getCriteria()->getFrequency() == Criteria::FREQUENCY_PUNCTUAL) {
                        $outward->setDate($matching['offer']->getProposalOffer()->getCriteria()->getFromDate());
                    } else {
                        $outward->setDate($proposal->getCriteria()->getFromDate());
                    }
                    // time
                    // the carpooler is driver, the proposal owner is passenger
                    // we have to calculate the starting time using the carpooler time
                    // we init the time to the one of the carpooler
                    if ($matching['offer']->getProposalOffer()->getCriteria()->getFrequency() == Criteria::FREQUENCY_PUNCTUAL) {
                        // the carpooler proposal is punctual, we take the fromTime
                        $fromTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getFromTime();
                    } else {
                        // the carpooler proposal is regular, we have to take the search/ad day's time
                        switch ($proposal->getCriteria()->getFromDate()->format('w')) {
                            case 0: {
                                $fromTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getSunTime();
                                break;
                            }
                            case 1: {
                                $fromTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getMonTime();
                                break;
                            }
                            case 2: {
                                $fromTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getTueTime();
                                break;
                            }
                            case 3: {
                                $fromTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getWedTime();
                                break;
                            }
                            case 4: {
                                $fromTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getThuTime();
                                break;
                            }
                            case 5: {
                                $fromTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getFriTime();
                                break;
                            }
                            case 6: {
                                $fromTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getSatTime();
                                break;
                            }
                        }
                    }
                    // we search the pickup duration
                    $filters = $matching['offer']->getFilters();
                    $pickupDuration = null;
                    foreach ($filters['route'] as $value) {
                        if ($value['candidate'] == 2 && $value['position'] == 0) {
                            $pickupDuration = (int)round($value['duration']);
                            break;
                        }
                    }
                    $driverFromTime = clone $fromTime;
                    if ($pickupDuration) {
                        $fromTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                    }
                    $outward->setTime($fromTime);
                } else {
                    // the search or ad is regular => no date
                    // we have to find common days (if it's a search the common days should be the carpooler days)
                    // we check if pickup times have been calculated already
                    // we set the global time for each day, we will erase it if we discover that all days have not the same time
                    // this way we are sure that if all days have the same time, the global time will be set and ok
                    if (isset($matching['offer']->getFilters()['pickup'])) {
                        // we have pickup times, it must be the matching results of an ad (and not a search)
                        // the carpooler is driver, the proposal owner is passenger : we use his time as it must be set
                        if (isset($matching['offer']->getFilters()['pickup']['monMinPickupTime']) && isset($matching['offer']->getFilters()['pickup']['monMaxPickupTime'])) {
                            $outward->setMonCheck(true);
                            $outward->setMonTime($proposal->getCriteria()->getMonTime());
                            $outward->setTime($proposal->getCriteria()->getMonTime());
                        }
                        if (isset($matching['offer']->getFilters()['pickup']['tueMinPickupTime']) && isset($matching['offer']->getFilters()['pickup']['tueMaxPickupTime'])) {
                            $outward->setTueCheck(true);
                            $outward->setTueTime($proposal->getCriteria()->getTueTime());
                            $outward->setTime($proposal->getCriteria()->getTueTime());
                        }
                        if (isset($matching['offer']->getFilters()['pickup']['wedMinPickupTime']) && isset($matching['offer']->getFilters()['pickup']['wedMaxPickupTime'])) {
                            $outward->setWedCheck(true);
                            $outward->setWedTime($proposal->getCriteria()->getWedTime());
                            $outward->setTime($proposal->getCriteria()->getWedTime());
                        }
                        if (isset($matching['offer']->getFilters()['pickup']['thuMinPickupTime']) && isset($matching['offer']->getFilters()['pickup']['thuMaxPickupTime'])) {
                            $outward->setThuCheck(true);
                            $outward->setThuTime($proposal->getCriteria()->getThuTime());
                            $outward->setTime($proposal->getCriteria()->getThuTime());
                        }
                        if (isset($matching['offer']->getFilters()['pickup']['friMinPickupTime']) && isset($matching['offer']->getFilters()['pickup']['friMaxPickupTime'])) {
                            $outward->setFriCheck(true);
                            $outward->setFriTime($proposal->getCriteria()->getFriTime());
                            $outward->setTime($proposal->getCriteria()->getFriTime());
                        }
                        if (isset($matching['offer']->getFilters()['pickup']['satMinPickupTime']) && isset($matching['offer']->getFilters()['pickup']['satMaxPickupTime'])) {
                            $outward->setSatCheck(true);
                            $outward->setSatTime($proposal->getCriteria()->getSatTime());
                            $outward->setTime($proposal->getCriteria()->getSatTime());
                        }
                        if (isset($matching['offer']->getFilters()['pickup']['sunMinPickupTime']) && isset($matching['offer']->getFilters()['pickup']['sunMaxPickupTime'])) {
                            $outward->setSunCheck(true);
                            $outward->setSunTime($proposal->getCriteria()->getSunTime());
                            $outward->setTime($proposal->getCriteria()->getSunTime());
                        }
                        $driverFromTime = $outward->getTime();
                    } else {
                        // no pick up times, it must be the matching results of a search (and not an ad)
                        // the days are the carpooler days
                        $outward->setMonCheck($matching['offer']->getProposalOffer()->getCriteria()->isMonCheck());
                        $outward->setTueCheck($matching['offer']->getProposalOffer()->getCriteria()->isTueCheck());
                        $outward->setWedCheck($matching['offer']->getProposalOffer()->getCriteria()->isWedCheck());
                        $outward->setThuCheck($matching['offer']->getProposalOffer()->getCriteria()->isThuCheck());
                        $outward->setFriCheck($matching['offer']->getProposalOffer()->getCriteria()->isFriCheck());
                        $outward->setSatCheck($matching['offer']->getProposalOffer()->getCriteria()->isSatCheck());
                        $outward->setSunCheck($matching['offer']->getProposalOffer()->getCriteria()->isSunCheck());
                        // we calculate the starting time so that the driver will get the carpooler on the carpooler time
                        // even if we don't use them, maybe we'll need them in the future
                        $filters = $matching['offer']->getFilters();
                        $pickupDuration = null;
                        foreach ($filters['route'] as $value) {
                            if ($value['candidate'] == 2 && $value['position'] == 0) {
                                $pickupDuration = (int)round($value['duration']);
                                break;
                            }
                        }
                        // we init the time to the one of the carpooler
                        if ($matching['offer']->getProposalOffer()->getCriteria()->isMonCheck()) {
                            $monTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getMonTime();
                            $driverFromTime = clone $monTime;
                            if ($pickupDuration) {
                                $monTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setMonTime($monTime);
                            $outward->setTime($monTime);
                        }
                        if ($matching['offer']->getProposalOffer()->getCriteria()->isTueCheck()) {
                            $tueTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getTueTime();
                            $driverFromTime = clone $tueTime;
                            if ($pickupDuration) {
                                $tueTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setTueTime($tueTime);
                            $outward->setTime($tueTime);
                        }
                        if ($matching['offer']->getProposalOffer()->getCriteria()->isWedCheck()) {
                            $wedTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getWedTime();
                            $driverFromTime = clone $wedTime;
                            if ($pickupDuration) {
                                $wedTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setWedTime($wedTime);
                            $outward->setTime($wedTime);
                        }
                        if ($matching['offer']->getProposalOffer()->getCriteria()->isThuCheck()) {
                            $thuTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getThuTime();
                            $driverFromTime = clone $thuTime;
                            if ($pickupDuration) {
                                $thuTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setThuTime($thuTime);
                            $outward->setTime($thuTime);
                        }
                        if ($matching['offer']->getProposalOffer()->getCriteria()->isFriCheck()) {
                            $friTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getFriTime();
                            $driverFromTime = clone $friTime;
                            if ($pickupDuration) {
                                $friTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setFriTime($friTime);
                            $outward->setTime($friTime);
                        }
                        if ($matching['offer']->getProposalOffer()->getCriteria()->isSatCheck()) {
                            $satTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getSatTime();
                            $driverFromTime = clone $satTime;
                            if ($pickupDuration) {
                                $satTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setSatTime($satTime);
                            $outward->setTime($satTime);
                        }
                        if ($matching['offer']->getProposalOffer()->getCriteria()->isSunCheck()) {
                            $sunTime = clone $matching['offer']->getProposalOffer()->getCriteria()->getSunTime();
                            $driverFromTime = clone $sunTime;
                            if ($pickupDuration) {
                                $sunTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $outward->setSunTime($sunTime);
                            $outward->setTime($sunTime);
                        }
                    }
                    $outward->setMultipleTimes();
                    if ($outward->hasMultipleTimes()) {
                        $outward->setTime(null);
                        $driverFromTime = null;
                    }
                    // fromDate is the max between the search date and the fromDate of the matching proposal
                    $outward->setFromDate(max(
                        $matching['offer']->getProposalOffer()->getCriteria()->getFromDate(),
                        $proposal->getCriteria()->getFromDate()
                    ));
                    $outward->setToDate($matching['offer']->getProposalOffer()->getCriteria()->getToDate());
                }
                // waypoints of the outward
                $waypoints = [];
                $time = $driverFromTime ? clone $driverFromTime : null;
                // we will have to compute the number of steps fo reach candidate
                $steps = [
                    'requester' => 0,
                    'carpooler' => 0
                ];
                // first pass to get the maximum position fo each candidate
                foreach ($matching['offer']->getFilters()['route'] as $key=>$waypoint) {
                    if ($waypoint['candidate'] == 2 && (int)$waypoint['position']>$steps['requester']) {
                        $steps['requester'] = (int)$waypoint['position'];
                    } elseif ($waypoint['candidate'] == 1 && (int)$waypoint['position']>$steps['carpooler']) {
                        $steps['carpooler'] = (int)$waypoint['position'];
                    }
                }
                // second pass to fill the waypoints array
                foreach ($matching['offer']->getFilters()['route'] as $key=>$waypoint) {
                    $curTime = null;
                    if ($time) {
                        $curTime = clone $time;
                    }
                    if ($curTime) {
                        $curTime->add(new \DateInterval('PT' . (int)round($waypoint['duration']) . 'S'));
                    }
                    $waypoints[$key] = [
                        'id' => $key,
                        'person' => $waypoint['candidate'] == 2 ? 'requester' : 'carpooler',
                        'role' => $waypoint['candidate'] == 1 ? 'driver' : 'passenger',
                        'time' =>  $curTime,
                        'address' => $waypoint['address'],
                        'type' => $waypoint['position'] == '0' ? 'origin' :
                            (
                                ($waypoint['candidate'] == 2) ? ((int)$waypoint['position'] == $steps['requester'] ? 'destination' : 'step') :
                                ((int)$waypoint['position'] == $steps['carpooler'] ? 'destination' : 'step')
                            )
                    ];
                    // origin and destination guess
                    if ($waypoint['candidate'] == 1 && $waypoint['position'] == '0') {
                        $outward->setOrigin($waypoint['address']);
                        $outward->setOriginDriver($waypoint['address']);
                    } elseif ($waypoint['candidate'] == 1 && (int)$waypoint['position'] == $steps['carpooler']) {
                        $outward->setDestination($waypoint['address']);
                        $outward->setDestinationDriver($waypoint['address']);
                    } elseif ($waypoint['candidate'] == 2 && $waypoint['position'] == '0') {
                        $outward->setOriginPassenger($waypoint['address']);
                    } elseif ($waypoint['candidate'] == 2 && (int)$waypoint['position'] == $steps['requester']) {
                        $outward->setDestinationPassenger($waypoint['address']);
                    }
                }
                $outward->setWaypoints($waypoints);
                
                // statistics
                $outward->setOriginalDistance($matching['offer']->getFilters()['originalDistance']);
                $outward->setAcceptedDetourDistance($matching['offer']->getFilters()['acceptedDetourDistance']);
                $outward->setNewDistance($matching['offer']->getFilters()['newDistance']);
                $outward->setDetourDistance($matching['offer']->getFilters()['detourDistance']);
                $outward->setDetourDistancePercent($matching['offer']->getFilters()['detourDistancePercent']);
                $outward->setOriginalDuration($matching['offer']->getFilters()['originalDuration']);
                $outward->setAcceptedDetourDuration($matching['offer']->getFilters()['acceptedDetourDuration']);
                $outward->setNewDuration($matching['offer']->getFilters()['newDuration']);
                $outward->setDetourDuration($matching['offer']->getFilters()['detourDuration']);
                $outward->setDetourDurationPercent($matching['offer']->getFilters()['detourDurationPercent']);
                $outward->setCommonDistance($matching['offer']->getFilters()['commonDistance']);

                // prices

                // we set the prices of the driver (the carpooler)
                $outward->setDriverPriceKm($matching['offer']->getProposalOffer()->getCriteria()->getPriceKm());
                $outward->setDriverOriginalPrice($matching['offer']->getProposalOffer()->getCriteria()->getPrice());
                $outward->setDriverOriginalRoundedPrice((string)$this->formatDataManager->roundPrice((float)$outward->getDriverOriginalPrice(), $proposal->getCriteria()->getFrequency()));
                
                // we set the prices of the passenger (the requester)
                if ($proposal->getCriteria()->getPriceKm()) {
                    $outward->setPassengerPriceKm($proposal->getCriteria()->getPriceKm());
                } else {
                    // otherwise we use the common price
                    $outward->setPassengerPriceKm($this->params['defaultPriceKm']);
                }
                // if the requester price is set we use it
                if ($proposal->getCriteria()->getPrice()) {
                    $outward->setPassengerOriginalPrice($proposal->getCriteria()->getPrice());
                } else {
                    // otherwise we use the common price
                    $outward->setPassengerOriginalPrice((string)((int)$matching['offer']->getFilters()['commonDistance']*(float)$outward->getPassengerPriceKm()/1000));
                }
                $outward->setPassengerOriginalRoundedPrice((string)$this->formatDataManager->roundPrice((float)$outward->getPassengerOriginalPrice(), $proposal->getCriteria()->getFrequency()));
                
                // the computed price is the price to be paid by the passenger
                // it's ((common distance + detour distance) * driver price by km)
                $outward->setComputedPrice((string)(((int)$matching['offer']->getFilters()['commonDistance']+(int)$matching['offer']->getFilters()['detourDistance'])*(float)$outward->getDriverPriceKm()/1000));
                $outward->setComputedRoundedPrice((string)$this->formatDataManager->roundPrice((float)$outward->getComputedPrice(), $proposal->getCriteria()->getFrequency()));
                $resultPassenger->setOutward($outward);

                // return trip, only for regular trip for now
                if ($matching['offer']->getProposalOffer()->getProposalLinked() && $proposal->getCriteria()->getFrequency() == Criteria::FREQUENCY_REGULAR && $matching['offer']->getProposalOffer()->getCriteria()->getFrequency() == Criteria::FREQUENCY_REGULAR) {
                    $offerProposalLinked = $matching['offer']->getProposalOffer()->getProposalLinked();
                    $matchingRelated = $matching['offer']->getMatchingRelated();

                    // /!\ we only treat the return days /!\
                    $return = new ResultItem();
                    // we use the carpooler days as we don't have a matching here
                    $return->setMonCheck($offerProposalLinked->getCriteria()->isMonCheck());
                    $return->setTueCheck($offerProposalLinked->getCriteria()->isTueCheck());
                    $return->setWedCheck($offerProposalLinked->getCriteria()->isWedCheck());
                    $return->setThuCheck($offerProposalLinked->getCriteria()->isThuCheck());
                    $return->setFriCheck($offerProposalLinked->getCriteria()->isFriCheck());
                    $return->setSatCheck($offerProposalLinked->getCriteria()->isSatCheck());
                    $return->setSunCheck($offerProposalLinked->getCriteria()->isSunCheck());
                    $return->setFromDate($offerProposalLinked->getCriteria()->getFromDate());
                    $return->setToDate($offerProposalLinked->getCriteria()->getToDate());
                    
                    if ($matchingRelated) {
                        // we calculate the starting time so that the driver will get the carpooler on the carpooler time
                        // even if we don't use them, maybe we'll need them in the future
                        $filters = $matchingRelated->getFilters();
                        $pickupDuration = null;
                        foreach ($filters['route'] as $value) {
                            if ($value['candidate'] == 2 && $value['position'] == 0) {
                                $pickupDuration = (int)round($value['duration']);
                                break;
                            }
                        }
                        // we init the time to the one of the carpooler
                        if ($offerProposalLinked->getCriteria()->isMonCheck()) {
                            $monTime = clone $offerProposalLinked->getCriteria()->getMonTime();
                            $driverFromTime = clone $monTime;
                            if ($pickupDuration) {
                                $monTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setMonTime($monTime);
                            $return->setTime($monTime);
                        }
                        if ($offerProposalLinked->getCriteria()->isTueCheck()) {
                            $tueTime = clone $offerProposalLinked->getCriteria()->getTueTime();
                            $driverFromTime = clone $tueTime;
                            if ($pickupDuration) {
                                $tueTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setTueTime($tueTime);
                            $return->setTime($tueTime);
                        }
                        if ($offerProposalLinked->getCriteria()->isWedCheck()) {
                            $wedTime = clone $offerProposalLinked->getCriteria()->getWedTime();
                            $driverFromTime = clone $wedTime;
                            if ($pickupDuration) {
                                $wedTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setWedTime($wedTime);
                            $return->setTime($wedTime);
                        }
                        if ($offerProposalLinked->getCriteria()->isThuCheck()) {
                            $thuTime = clone $offerProposalLinked->getCriteria()->getThuTime();
                            $driverFromTime = clone $thuTime;
                            if ($pickupDuration) {
                                $thuTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setThuTime($thuTime);
                            $return->setTime($thuTime);
                        }
                        if ($offerProposalLinked->getCriteria()->isFriCheck()) {
                            $friTime = clone $offerProposalLinked->getCriteria()->getFriTime();
                            $driverFromTime = clone $friTime;
                            if ($pickupDuration) {
                                $friTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setFriTime($friTime);
                            $return->setTime($friTime);
                        }
                        if ($offerProposalLinked->getCriteria()->isSatCheck()) {
                            $satTime = clone $offerProposalLinked->getCriteria()->getSatTime();
                            $driverFromTime = clone $satTime;
                            if ($pickupDuration) {
                                $satTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setSatTime($satTime);
                            $return->setTime($satTime);
                        }
                        if ($offerProposalLinked->getCriteria()->isSunCheck()) {
                            $sunTime = clone $offerProposalLinked->getCriteria()->getSunTime();
                            $driverFromTime = clone $sunTime;
                            if ($pickupDuration) {
                                $sunTime->add(new \DateInterval('PT' . $pickupDuration . 'S'));
                            }
                            $return->setSunTime($sunTime);
                            $return->setTime($sunTime);
                        }
                        // fromDate is the max between the search date and the fromDate of the matching proposal
                        $return->setFromDate(max(
                            $matchingRelated->getProposalOffer()->getCriteria()->getFromDate(),
                            $proposal->getCriteria()->getFromDate()
                        ));
                        $return->setToDate($matchingRelated->getProposalOffer()->getCriteria()->getToDate());
                        
                        // waypoints of the return
                        $waypoints = [];
                        $time = $driverFromTime ? clone $driverFromTime : null;
                        // we will have to compute the number of steps for each candidate
                        $steps = [
                            'requester' => 0,
                            'carpooler' => 0
                        ];
                        // first pass to get the maximum position for each candidate
                        foreach ($matchingRelated->getFilters()['route'] as $key=>$waypoint) {
                            if ($waypoint['candidate'] == 2 && (int)$waypoint['position']>$steps['requester']) {
                                $steps['requester'] = (int)$waypoint['position'];
                            } elseif ($waypoint['candidate'] == 1 && (int)$waypoint['position']>$steps['carpooler']) {
                                $steps['carpooler'] = (int)$waypoint['position'];
                            }
                        }
                        // second pass to fill the waypoints array
                        foreach ($matchingRelated->getFilters()['route'] as $key=>$waypoint) {
                            $curTime = null;
                            if ($time) {
                                $curTime = clone $time;
                            }
                            if ($curTime) {
                                $curTime->add(new \DateInterval('PT' . (int)round($waypoint['duration']) . 'S'));
                            }
                            $waypoints[$key] = [
                                'id' => $key,
                                'person' => $waypoint['candidate'] == 2 ? 'requester' : 'carpooler',
                                'role' => $waypoint['candidate'] == 1 ? 'driver' : 'passenger',
                                'time' =>  $curTime,
                                'address' => $waypoint['address'],
                                'type' => $waypoint['position'] == '0' ? 'origin' :
                                    (
                                        ($waypoint['candidate'] == 2) ? ((int)$waypoint['position'] == $steps['requester'] ? 'destination' : 'step') :
                                        ((int)$waypoint['position'] == $steps['carpooler'] ? 'destination' : 'step')
                                    )
                            ];
                            // origin and destination guess
                            if ($waypoint['candidate'] == 1 && $waypoint['position'] == '0') {
                                $return->setOrigin($waypoint['address']);
                                $return->setOriginDriver($waypoint['address']);
                            } elseif ($waypoint['candidate'] == 1 && (int)$waypoint['position'] == $steps['carpooler']) {
                                $return->setDestination($waypoint['address']);
                                $return->setDestinationDriver($waypoint['address']);
                            } elseif ($waypoint['candidate'] == 2 && $waypoint['position'] == '0') {
                                $return->setOriginPassenger($waypoint['address']);
                            } elseif ($waypoint['candidate'] == 2 && (int)$waypoint['position'] == $steps['requester']) {
                                $return->setDestinationPassenger($waypoint['address']);
                            }
                        }
                        $return->setWaypoints($waypoints);
                        
                        // statistics
                        if ($matchingRelated->getFilters()['originalDistance']) {
                            $return->setOriginalDistance($matchingRelated->getFilters()['originalDistance']);
                        }
                        if ($matchingRelated->getFilters()['acceptedDetourDistance']) {
                            $return->setAcceptedDetourDistance($matchingRelated->getFilters()['acceptedDetourDistance']);
                        }
                        if ($matchingRelated->getFilters()['newDistance']) {
                            $return->setNewDistance($matchingRelated->getFilters()['newDistance']);
                        }
                        if ($matchingRelated->getFilters()['detourDistance']) {
                            $return->setDetourDistance($matchingRelated->getFilters()['detourDistance']);
                        }
                        if ($matchingRelated->getFilters()['detourDistancePercent']) {
                            $return->setDetourDistancePercent($matchingRelated->getFilters()['detourDistancePercent']);
                        }
                        if ($matchingRelated->getFilters()['originalDuration']) {
                            $return->setOriginalDuration($matchingRelated->getFilters()['originalDuration']);
                        }
                        if ($matchingRelated->getFilters()['acceptedDetourDuration']) {
                            $return->setAcceptedDetourDuration($matchingRelated->getFilters()['acceptedDetourDuration']);
                        }
                        if ($matchingRelated->getFilters()['newDuration']) {
                            $return->setNewDuration($matchingRelated->getFilters()['newDuration']);
                        }
                        if ($matchingRelated->getFilters()['detourDuration']) {
                            $return->setDetourDuration($matchingRelated->getFilters()['detourDuration']);
                        }
                        if ($matchingRelated->getFilters()['detourDurationPercent']) {
                            $return->setDetourDurationPercent($matchingRelated->getFilters()['detourDurationPercent']);
                        }
                        if ($matchingRelated->getFilters()['commonDistance']) {
                            $return->setCommonDistance($matchingRelated->getFilters()['commonDistance']);
                        }

                        // prices

                        // we set the prices of the driver (the carpooler)
                        // if the requester price per km is set we use it
                        $return->setDriverPriceKm($offerProposalLinked->getCriteria()->getPriceKm());
                        $return->setDriverOriginalPrice($offerProposalLinked->getCriteria()->getPrice());
                        $return->setDriverOriginalRoundedPrice((string)$this->formatDataManager->roundPrice((float)$return->getDriverOriginalPrice(), $proposal->getCriteria()->getFrequency()));
                        
                        // we set the prices of the passenger (the requester)
                        // we don't have a proposalLinked for the proposal, we use the proposal
                        if ($proposal->getCriteria()->getPriceKm()) {
                            $return->setPassengerPriceKm($proposal->getCriteria()->getPriceKm());
                        } else {
                            // otherwise we use the common price
                            $return->setPassengerPriceKm($this->params['defaultPriceKm']);
                        }
                        // if the requester price is set we use it
                        if ($proposal->getCriteria()->getPrice()) {
                            $return->setPassengerOriginalPrice($proposal->getCriteria()->getPrice());
                        } else {
                            // otherwise we use the common price
                            $return->setPassengerOriginalPrice((string)((int)$matchingRelated->getFilters()['commonDistance']*(float)$return->getPassengerPriceKm()/1000));
                        }
                        $return->setPassengerOriginalRoundedPrice((string)$this->formatDataManager->roundPrice((float)$return->getPassengerOriginalPrice(), $proposal->getCriteria()->getFrequency()));

                        // the computed price is the price to be paid by the passenger
                        // it's ((common distance + detour distance) * driver price by km)
                        $return->setComputedPrice((string)(((int)$matchingRelated->getFilters()['commonDistance']+(int)$matchingRelated->getFilters()['detourDistance'])*(float)$return->getDriverPriceKm()/1000));
                        $return->setComputedRoundedPrice((string)$this->formatDataManager->roundPrice((float)$return->getComputedPrice(), $proposal->getCriteria()->getFrequency()));
                    }
                    $return->setMultipleTimes();
                    if ($return->hasMultipleTimes()) {
                        $return->setTime(null);
                    }
                    
                    $resultPassenger->setReturn($return);
                }

                // seats
                $resultPassenger->setSeats($matching['offer']->getProposalOffer()->getCriteria()->getSeats() ? $matching['offer']->getProposalOffer()->getCriteria()->getSeats() : 1);
                $result->setResultPassenger($resultPassenger);
            }

            /**********************************************************************
             * global origin / destination / date / time / seats / price / return *
             **********************************************************************/
            
            // the following are used to display the summarized information about the result

            // origin / destination
            // we display the origin and destination of the passenger for his outward trip
            // if the carpooler can be driver and passenger, we choose to consider him as driver as he's the first to publish
            // we also set the originFirst and destinationLast to indicate if the driver origin / destination are different than the passenger ones

            // we first get the origin and destination of the requester
            $requesterOrigin = null;
            $requesterDestination = null;
            foreach ($proposal->getWaypoints() as $waypoint) {
                if ($waypoint->getPosition() == 0) {
                    $requesterOrigin = $waypoint->getAddress();
                }
                if ($waypoint->isDestination()) {
                    $requesterDestination = $waypoint->getAddress();
                }
            }
            if ($result->getResultDriver() && !$result->getResultPassenger()) {
                // the carpooler is passenger only, we use his origin and destination
                $result->setOrigin($result->getResultDriver()->getOutward()->getOrigin());
                $result->setDestination($result->getResultDriver()->getOutward()->getDestination());
                // we check if his origin and destination are first and last of the whole journey
                // we use the gps coordinates
                $result->setOriginFirst(false);
                if ($result->getOrigin()->getLatitude() == $requesterOrigin->getLatitude() && $result->getOrigin()->getLongitude() == $requesterOrigin->getLongitude()) {
                    $result->setOriginFirst(true);
                }
                $result->setDestinationLast(false);
                if ($result->getDestination()->getLatitude() == $requesterDestination->getLatitude() && $result->getDestination()->getLongitude() == $requesterDestination->getLongitude()) {
                    $result->setDestinationLast(true);
                }
                // driver and passenger origin/destination
                $result->setOriginDriver($result->getResultDriver()->getOutward()->getOriginDriver());
                $result->setDestinationDriver($result->getResultDriver()->getOutward()->getDestinationDriver());
                $result->setOriginPassenger($result->getResultDriver()->getOutward()->getOriginPassenger());
                $result->setDestinationPassenger($result->getResultDriver()->getOutward()->getDestinationPassenger());
            } else {
                // the carpooler can be driver, we use the requester origin and destination
                $result->setOrigin($requesterOrigin);
                $result->setDestination($requesterDestination);
                // we check if his origin and destination are first and last of the whole journey
                // we use the gps coordinates
                $result->setOriginFirst(false);
                if ($result->getOrigin()->getLatitude() == $result->getResultPassenger()->getOutward()->getOrigin()->getLatitude() && $result->getOrigin()->getLongitude() == $result->getResultPassenger()->getOutward()->getOrigin()->getLongitude()) {
                    $result->setOriginFirst(true);
                }
                $result->setDestinationLast(false);
                if ($result->getDestination()->getLatitude() == $result->getResultPassenger()->getOutward()->getDestination()->getLatitude() && $result->getDestination()->getLongitude() == $result->getResultPassenger()->getOutward()->getDestination()->getLongitude()) {
                    $result->setDestinationLast(true);
                }
                // driver and passenger origin/destination
                $result->setOriginDriver($result->getResultPassenger()->getOutward()->getOriginDriver());
                $result->setDestinationDriver($result->getResultPassenger()->getOutward()->getDestinationDriver());
                $result->setOriginPassenger($result->getResultPassenger()->getOutward()->getOriginPassenger());
                $result->setDestinationPassenger($result->getResultPassenger()->getOutward()->getDestinationPassenger());
            }

            // date / time / seats / price
            // if the request is regular, there is no date, but we keep a start date
            // otherwise we display the date of the matching proposal computed before depending on if the carpooler can be driver and/or passenger
            if ($result->getResultDriver() && !$result->getResultPassenger()) {
                // the carpooler is passenger only
                if ($result->getFrequency() == Criteria::FREQUENCY_PUNCTUAL) {
                    $result->setDate($result->getResultDriver()->getOutward()->getDate());
                    $result->setTime($result->getResultDriver()->getOutward()->getTime());
                } else {
                    $result->setStartDate($result->getResultDriver()->getOutward()->getFromDate());
                    $result->setToDate($result->getResultDriver()->getOutward()->getToDate());
                }
                $result->setPrice($result->getResultDriver()->getOutward()->getComputedPrice());
                $result->setRoundedPrice($result->getResultDriver()->getOutward()->getComputedRoundedPrice());
                $result->setSeats($result->getResultDriver()->getSeats());
            } else {
                // the carpooler is driver or passenger
                if ($result->getFrequency() == Criteria::FREQUENCY_PUNCTUAL) {
                    $result->setDate($result->getResultPassenger()->getOutward()->getDate());
                    $result->setTime($result->getResultPassenger()->getOutward()->getTime());
                } else {
                    $result->setStartDate($result->getResultPassenger()->getOutward()->getFromDate());
                    $result->setToDate($result->getResultPassenger()->getOutward()->getToDate());
                }
                $result->setPrice($result->getResultPassenger()->getOutward()->getComputedPrice());
                $result->setRoundedPrice($result->getResultPassenger()->getOutward()->getComputedRoundedPrice());
                $result->setSeats($result->getResultPassenger()->getSeats());
            }
            // regular days and times
            if ($result->getFrequencyResult() == Criteria::FREQUENCY_REGULAR) {
                if ($result->getResultDriver() && !$result->getResultPassenger()) {
                    // the carpooler is passenger only
                    $result->setMonCheck($result->getResultDriver()->getOutward()->isMonCheck() || ($result->getResultDriver()->getReturn() && $result->getResultDriver()->getReturn()->isMonCheck()));
                    $result->setTueCheck($result->getResultDriver()->getOutward()->isTueCheck() || ($result->getResultDriver()->getReturn() && $result->getResultDriver()->getReturn()->isTueCheck()));
                    $result->setWedCheck($result->getResultDriver()->getOutward()->isWedCheck() || ($result->getResultDriver()->getReturn() && $result->getResultDriver()->getReturn()->isWedCheck()));
                    $result->setThuCheck($result->getResultDriver()->getOutward()->isThuCheck() || ($result->getResultDriver()->getReturn() && $result->getResultDriver()->getReturn()->isThuCheck()));
                    $result->setFriCheck($result->getResultDriver()->getOutward()->isFriCheck() || ($result->getResultDriver()->getReturn() && $result->getResultDriver()->getReturn()->isFriCheck()));
                    $result->setSatCheck($result->getResultDriver()->getOutward()->isSatCheck() || ($result->getResultDriver()->getReturn() && $result->getResultDriver()->getReturn()->isSatCheck()));
                    $result->setSunCheck($result->getResultDriver()->getOutward()->isSunCheck() || ($result->getResultDriver()->getReturn() && $result->getResultDriver()->getReturn()->isSunCheck()));
                    if (!$result->getResultDriver()->getOutward()->hasMultipleTimes()) {
                        if ($result->getResultDriver()->getOutward()->getMonTime()) {
                            $result->setOutwardTime($result->getResultDriver()->getOutward()->getMonTime());
                        } elseif ($result->getResultDriver()->getOutward()->getTueTime()) {
                            $result->setOutwardTime($result->getResultDriver()->getOutward()->getTueTime());
                        } elseif ($result->getResultDriver()->getOutward()->getWedTime()) {
                            $result->setOutwardTime($result->getResultDriver()->getOutward()->getWedTime());
                        } elseif ($result->getResultDriver()->getOutward()->getThuTime()) {
                            $result->setOutwardTime($result->getResultDriver()->getOutward()->getThuTime());
                        } elseif ($result->getResultDriver()->getOutward()->getFriTime()) {
                            $result->setOutwardTime($result->getResultDriver()->getOutward()->getFriTime());
                        } elseif ($result->getResultDriver()->getOutward()->getSatTime()) {
                            $result->setOutwardTime($result->getResultDriver()->getOutward()->getSatTime());
                        } elseif ($result->getResultDriver()->getOutward()->getSunTime()) {
                            $result->setOutwardTime($result->getResultDriver()->getOutward()->getSunTime());
                        }
                    }
                    if ($result->getResultDriver()->getReturn() && !$result->getResultDriver()->getReturn()->hasMultipleTimes()) {
                        if ($result->getResultDriver()->getReturn()->getMonTime()) {
                            $result->setReturnTime($result->getResultDriver()->getReturn()->getMonTime());
                        } elseif ($result->getResultDriver()->getReturn()->getTueTime()) {
                            $result->setReturnTime($result->getResultDriver()->getReturn()->getTueTime());
                        } elseif ($result->getResultDriver()->getReturn()->getWedTime()) {
                            $result->setReturnTime($result->getResultDriver()->getReturn()->getWedTime());
                        } elseif ($result->getResultDriver()->getReturn()->getThuTime()) {
                            $result->setReturnTime($result->getResultDriver()->getReturn()->getThuTime());
                        } elseif ($result->getResultDriver()->getReturn()->getFriTime()) {
                            $result->setReturnTime($result->getResultDriver()->getReturn()->getFriTime());
                        } elseif ($result->getResultDriver()->getReturn()->getSatTime()) {
                            $result->setReturnTime($result->getResultDriver()->getReturn()->getSatTime());
                        } elseif ($result->getResultDriver()->getReturn()->getSunTime()) {
                            $result->setReturnTime($result->getResultDriver()->getReturn()->getSunTime());
                        }
                    }
                } else {
                    // the carpooler is driver or passenger
                    $result->setMonCheck($result->getResultPassenger()->getOutward()->isMonCheck() || ($result->getResultPassenger()->getReturn() && $result->getResultPassenger()->getReturn()->isMonCheck()));
                    $result->setTueCheck($result->getResultPassenger()->getOutward()->isTueCheck() || ($result->getResultPassenger()->getReturn() && $result->getResultPassenger()->getReturn()->isTueCheck()));
                    $result->setWedCheck($result->getResultPassenger()->getOutward()->isWedCheck() || ($result->getResultPassenger()->getReturn() && $result->getResultPassenger()->getReturn()->isWedCheck()));
                    $result->setThuCheck($result->getResultPassenger()->getOutward()->isThuCheck() || ($result->getResultPassenger()->getReturn() && $result->getResultPassenger()->getReturn()->isThuCheck()));
                    $result->setFriCheck($result->getResultPassenger()->getOutward()->isFriCheck() || ($result->getResultPassenger()->getReturn() && $result->getResultPassenger()->getReturn()->isFriCheck()));
                    $result->setSatCheck($result->getResultPassenger()->getOutward()->isSatCheck() || ($result->getResultPassenger()->getReturn() && $result->getResultPassenger()->getReturn()->isSatCheck()));
                    $result->setSunCheck($result->getResultPassenger()->getOutward()->isSunCheck() || ($result->getResultPassenger()->getReturn() && $result->getResultPassenger()->getReturn()->isSunCheck()));
                    if (!$result->getResultPassenger()->getOutward()->hasMultipleTimes()) {
                        if ($result->getResultPassenger()->getOutward()->getMonTime()) {
                            $result->setOutwardTime($result->getResultPassenger()->getOutward()->getMonTime());
                        } elseif ($result->getResultPassenger()->getOutward()->getTueTime()) {
                            $result->setOutwardTime($result->getResultPassenger()->getOutward()->getTueTime());
                        } elseif ($result->getResultPassenger()->getOutward()->getWedTime()) {
                            $result->setOutwardTime($result->getResultPassenger()->getOutward()->getWedTime());
                        } elseif ($result->getResultPassenger()->getOutward()->getThuTime()) {
                            $result->setOutwardTime($result->getResultPassenger()->getOutward()->getThuTime());
                        } elseif ($result->getResultPassenger()->getOutward()->getFriTime()) {
                            $result->setOutwardTime($result->getResultPassenger()->getOutward()->getFriTime());
                        } elseif ($result->getResultPassenger()->getOutward()->getSatTime()) {
                            $result->setOutwardTime($result->getResultPassenger()->getOutward()->getSatTime());
                        } elseif ($result->getResultPassenger()->getOutward()->getSunTime()) {
                            $result->setOutwardTime($result->getResultPassenger()->getOutward()->getSunTime());
                        }
                    }
                    if ($result->getResultPassenger()->getReturn() && !$result->getResultPassenger()->getReturn()->hasMultipleTimes()) {
                        if ($result->getResultPassenger()->getReturn()->getMonTime()) {
                            $result->setReturnTime($result->getResultPassenger()->getReturn()->getMonTime());
                        } elseif ($result->getResultPassenger()->getReturn()->getTueTime()) {
                            $result->setReturnTime($result->getResultPassenger()->getReturn()->getTueTime());
                        } elseif ($result->getResultPassenger()->getReturn()->getWedTime()) {
                            $result->setReturnTime($result->getResultPassenger()->getReturn()->getWedTime());
                        } elseif ($result->getResultPassenger()->getReturn()->getThuTime()) {
                            $result->setReturnTime($result->getResultPassenger()->getReturn()->getThuTime());
                        } elseif ($result->getResultPassenger()->getReturn()->getFriTime()) {
                            $result->setReturnTime($result->getResultPassenger()->getReturn()->getFriTime());
                        } elseif ($result->getResultPassenger()->getReturn()->getSatTime()) {
                            $result->setReturnTime($result->getResultPassenger()->getReturn()->getSatTime());
                        } elseif ($result->getResultPassenger()->getReturn()->getSunTime()) {
                            $result->setReturnTime($result->getResultPassenger()->getReturn()->getSunTime());
                        }
                    }
                }
            }

            // return trip ?
            $result->setReturn(false);
            if ($result->getResultDriver() && !$result->getResultPassenger()) {
                // the carpooler is passenger only
                if (!is_null($result->getResultDriver()->getReturn())) {
                    $result->setReturn(true);
                }
            } else {
                // the carpooler is driver or passenger
                if (!is_null($result->getResultPassenger()->getReturn())) {
                    $result->setReturn(true);
                }
            }

            $results[] = $result;
        }
        return $results;
    }
}