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

declare(strict_types=1);

namespace App\Geography\Service;

use App\Event\Repository\EventRepository;
use App\Geography\Repository\AddressRepository;
use App\Geography\Service\Geocoder\MobicoopGeocoder;
use App\Geography\Service\Point\EventPointProvider;
use App\Geography\Service\Point\MobicoopGeocoderPointProvider;
use App\Geography\Service\Point\RelayPointPointProvider;
use App\Geography\Service\Point\UserPointProvider;
use App\RelayPoint\Repository\RelayPointRepository;
use App\User\Entity\User;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Point searcher.
 */
class PointSearcher
{
    private $providers;
    private $reverseProviders;

    public function __construct(
        MobicoopGeocoder $mobicoopGeocoder,
        RelayPointRepository $relayPointRepository,
        EventRepository $eventRepository,
        AddressRepository $addressRepository,
        TranslatorInterface $translator,
        Security $security,
        int $maxRelayPointResults,
        int $maxEventResults,
        int $maxUserResults,
        array $prioritizeCentroid = null,
        array $prioritizeBox = null,
        string $prioritizeRegion = null,
        string $restrictCountry = null,
        array $exclusionTypes = [],
        array $relayPointParams
    ) {
        $user = $security->getUser();
        $userPointProvider = new UserPointProvider($addressRepository, $translator);
        if ($prioritizeCentroid) {
            $mobicoopGeocoder->setPrioritizeCentroid(
                $prioritizeCentroid['lon'],
                $prioritizeCentroid['lat']
            );
        }
        if ($prioritizeBox) {
            $mobicoopGeocoder->setPrioritizeBox(
                $prioritizeBox['minLon'],
                $prioritizeBox['minLat'],
                $prioritizeBox['maxLon'],
                $prioritizeBox['maxLat']
            );
        }
        if ($prioritizeRegion) {
            $mobicoopGeocoder->setPrioritizeRegion($prioritizeRegion);
        }
        if ($restrictCountry) {
            $mobicoopGeocoder->setRestrictCountry($restrictCountry);
        }
        if ($user instanceof User) {
            $userPointProvider->setUser($user);
            $userPointProvider->setMaxResults($maxUserResults);

            /**
             * @var null|User $user
             */
            foreach ($user->getAddresses() as $address) {
                if ($address->isHome()) {
                    $mobicoopGeocoder->setPrioritizeCentroid(
                        (float) $address->getLongitude(),
                        (float) $address->getLatitude()
                    );

                    break;
                }
            }
        }
        $mobicoopGeocoderPointProvider = new MobicoopGeocoderPointProvider($mobicoopGeocoder);
        $mobicoopGeocoderPointProvider->setExclusionTypes($exclusionTypes);

        $relayPointPointProvider = new RelayPointPointProvider($relayPointRepository);
        $relayPointPointProvider->setMaxResults($maxRelayPointResults);
        $relayPointPointProvider->setParams($relayPointParams);

        $eventPointProvider = new EventPointProvider($eventRepository);
        $eventPointProvider->setMaxResults($maxEventResults);

        $this->providers = [
            $mobicoopGeocoderPointProvider,
            $relayPointPointProvider,
            $eventPointProvider,
        ];

        if ($user instanceof User) {
            $this->providers[] = $userPointProvider;
        }

        $this->reverseProviders = [$mobicoopGeocoderPointProvider];
    }

    public function geocode(string $search): array
    {
        $points = [];
        foreach ($this->providers as $provider) {
            $points = array_merge($points, $provider->search(str_replace(['"', "'"], ' ', $search)));
        }

        return $points;
    }

    public function reverse(float $lon, float $lat): array
    {
        $points = [];
        foreach ($this->reverseProviders as $provider) {
            $points = array_merge($points, $provider->reverse($lon, $lat));
        }

        return $points;
    }
}
