<?php

/**
 * Copyright (c) 2020, MOBICOOP. All rights reserved.
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

namespace Mobicoop\Bundle\MobicoopBundle\Community\Service;

use Mobicoop\Bundle\MobicoopBundle\Api\Service\DataProvider;
use Mobicoop\Bundle\MobicoopBundle\Carpool\Entity\Ad;
use Mobicoop\Bundle\MobicoopBundle\Community\Entity\Community;
use Mobicoop\Bundle\MobicoopBundle\Community\Entity\CommunityUser;
use Mobicoop\Bundle\MobicoopBundle\Community\Entity\MCommunity;
use Mobicoop\Bundle\MobicoopBundle\RelayPoint\Entity\RelayPointMap;
use Mobicoop\Bundle\MobicoopBundle\Traits\HydraControllerTrait;
use Mobicoop\Bundle\MobicoopBundle\User\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Community management service.
 */
class CommunityManager
{
    use HydraControllerTrait;

    private $dataProvider;
    private $territoryFilter;
    private $router;

    /**
     * Constructor.
     */
    public function __construct(DataProvider $dataProvider, array $territoryFilter, UrlGeneratorInterface $router)
    {
        $this->dataProvider = $dataProvider;
        $this->dataProvider->setClass(Community::class);
        $this->territoryFilter = $territoryFilter;
        $this->router = $router;
    }

    /**
     * Create a community.
     *
     * @param Community
     *
     * @return null|Community
     */
    public function createCommunity(Community $community)
    {
        $response = $this->dataProvider->post($community);
        if (201 == $response->getCode()) {
            return $response->getValue();
        }

        return null;
    }

    /**
     * Get all communities for a user if given, and the list of all community.
     *
     * @param null|User $user The current user or null, if not logged
     * @param array     $data Data for the pagination like page,perPage,search
     *
     * @return null|array the communities found or null if not found
     */
    public function getAllCommunities($user, $data)
    {
        $perPage = (isset($data['perPage']) && !is_null($data['perPage'])) ? $data['perPage'] : null;
        $page = (isset($data['page']) && !is_null($data['page'])) ? $data['page'] : null;
        $search = (isset($data['search']) && !is_null($data['search'])) ? $data['search'] : [];
        $showAllCommunities = (isset($data['showAllCommunities']) && !is_null($data['showAllCommunities'])) ? $data['showAllCommunities'] : false;

        $order = [];
        if (isset($data['order'], $data['orderWay']) && !empty($data['order']) && !empty($data['orderWay'])) {
            $order[$data['order']] = $data['orderWay'];
        }

        // if ($user) {
        //     // We get all the communities
        //     $communities = $this->getCommunities($user->getId(), $perPage, $page, $search, $order, $showAllCommunities);
        //     // We get the communities of the user
        //     $communitiesUser = $this->getAllCommunityUser($user->getId());
        //     if ($communitiesUser != null) {
        //         foreach ($communitiesUser as $communityUser) {
        //             $returnCommunitiesUser[] = $communityUser->getCommunity();
        //         }
        //     }
        // } else {
        //     $communities = $this->getCommunities(null, $perPage, $page, $search, $order, $showAllCommunities);
        // }

        // We get all the communities
        $communities = $this->getCommunities(($user) ? $user->getId() : null, $perPage, $page, $search, $order, $showAllCommunities);
        $communitiesUser = [];
        if ($user) {
            // We get the communities of the user
            $communitiesUser = $this->getAllCommunityUser();
        }

        $return['communitiesMember'] = $communities->getMember();
        $return['communitiesTotalItems'] = $communities->getTotalItems();

        $return['communitiesUser'] = $communitiesUser;

        return $return;
    }

    /**
     * Get all communities.
     *
     * @param null|int $userId  The id of the user you want to know if he is already an accepted member of the community
     * @param null|int $perPage Number of items per page
     * @param null|int $page    Current page
     * @param array    $search  Array of search criterias
     * @param array    $order   Order criterias
     *
     * @return null|array the communities found or null if not found
     */
    public function getCommunities(?int $userId = null, ?int $perPage = null, ?int $page = null, array $search = [], array $order = [], bool $showAllCommunities = false)
    {
        $params = null;
        if (null !== $userId) {
            $params['userId'] = $userId;
        }
        if (null !== $perPage) {
            $params['perPage'] = $perPage;
        }
        if (null !== $page) {
            $params['page'] = $page;
        }
        if (null !== $showAllCommunities) {
            $params['showAllCommunities'] = $showAllCommunities;
        }
        if (count($search) > 0) {
            foreach ($search as $key => $value) {
                $params[$key] = $value;
            }
        }
        if (count($order) > 0) {
            $params['order'] = [];
            foreach ($order as $key => $value) {
                $params['order'][$key] = $value;
            }
        }
        if (count($this->territoryFilter) > 0) {
            $params['territory'] = $this->territoryFilter;
        }

        $response = $this->dataProvider->getCollection($params);
        if ($response->getCode() >= 200 && $response->getCode() <= 300) {
            return $response->getValue();
        }

        return $response->getValue();
    }

    /**
     * Get all communities available for a user.
     *
     * @return null|array the communities found or null if not found
     */
    public function getAvailableUserCommunities(?User $user)
    {
        $response = $this->dataProvider->getSpecialCollection('available', $user ? ['userId' => $user->getId()] : null);

        return $response->getValue();
    }

    /**
     * Get one community.
     *
     * @param mixed $id
     *
     * @return null|Community|int
     */
    public function getCommunity($id)
    {
        $response = $this->dataProvider->getItem($id);
        if (400 == $response->getCode()) {
            return $response->getCode();
        }

        return $response->getValue();
    }

    /**
     * Join a community.
     *
     * @return null|Community
     */
    public function joinCommunity(Community $community)
    {
        $this->dataProvider->setClass(Community::class);
        $response = $this->dataProvider->putSpecial($community, null, 'join');
        if (200 == $response->getCode()) {
            return $response->getValue();
        }

        return null;
    }

    /**
     * Leave a community.
     *
     * @return null|array|object
     */
    public function leaveCommunity(Community $community)
    {
        $this->dataProvider->setClass(Community::class);
        $response = $this->dataProvider->putSpecial($community, null, 'leave');
        if (201 == $response->getCode()) {
            return $response->getValue();
        }

        return null;
    }

    /**
     * Delete a community -> Use for delete community if an error occur with the image upload.
     *
     * @param int $id The id of the community to delete
     *
     * @return bool the result of the deletion
     */
    public function deleteCommunity(int $id)
    {
        $this->dataProvider->setClass(Community::class);
        $response = $this->dataProvider->delete($id);
        if (204 == $response->getCode()) {
            return true;
        }

        return false;
    }

    /**
     * Get the community_user of a user for a community.
     *
     * @param int $communityId Id of the community
     * @param int $userId      Id of the User to test
     */
    public function getCommunityUser(int $communityId, int $userId)
    {
        $this->dataProvider->setClass(CommunityUser::class);
        $response = $this->dataProvider->getCollection(['community' => $communityId, 'user' => $userId]);

        return $response->getValue()->getMember();
    }

    /**
     * Get all the community_user of a user.
     */
    public function getAllCommunityUser()
    {
        $this->dataProvider->setClass(User::class);
        $this->dataProvider->setFormat(DataProvider::RETURN_JSON);
        $response = $this->dataProvider->getSpecialCollection('communities');

        return $response->getValue();
    }

    /**
     * Check if a community with a specific name exists.
     */
    public function checkExists(string $name)
    {
        $response = $this->dataProvider->getSpecialCollection('exists', ['name' => $name]);

        return $response->getValue();
    }

    /**
     * get communities owned by the user.
     */
    public function getOwnedCommunities(int $userId)
    {
        $response = $this->dataProvider->getSpecialCollection('owned', ['userId' => $userId]);

        return $response->getValue()->getMember();
    }

    /**
     * Check if a User has a certain status in a community.
     *
     * @param int      $communityId Id of the community
     * @param int      $userId      Id of the User to test
     * @param null|int $status      Status to test
     */
    public function checkStatus(int $communityId, int $userId, ?int $status = null)
    {
        $params = [
            'community' => $communityId,
            'user' => $userId,
        ];

        (!is_null($status)) ? $params['status'] = $status : '';

        $this->dataProvider->setClass(CommunityUser::class);
        $response = $this->dataProvider->getCollection($params);

        return $response->getValue()->getMember();
    }

    /**
     * get the public infos of a community.
     *
     * @return null|Community
     */
    public function getPublicInfos(int $communityId)
    {
        $response = $this->dataProvider->getSpecialItem($communityId, 'public');

        return $response->getValue();
    }

    /**
     * Format the waypoint of ads for a ommunity (used in detail community).
     *
     * @return null|array Tha waypoints
     */
    public function formatWaypointForDetailCommunity(Community $community)
    {
        $ways = [];
        if (null != $community->getAds()) {
            foreach ($community->getAds() as $ad) {
                $origin = null;
                $destination = null;
                $isRegular = null;
                $date = null;

                if (Ad::FREQUENCY_REGULAR === $ad['frequency']) {
                    $isRegular = true;
                } else {
                    $date = new \DateTime($ad['outwardDate']);
                    $date = $date->format('Y-m-d');
                }
                $currentAd = [
                    'frequency' => (Ad::FREQUENCY_PUNCTUAL == $ad['frequency']) ? 'punctual' : 'regular',
                    'carpoolerFirstName' => $ad['user']['givenName'],
                    'carpoolerLastName' => $ad['user']['shortFamilyName'],
                    'waypoints' => [],
                ];
                foreach ($ad['outwardWaypoints'] as $waypoint) {
                    if (0 === $waypoint['position']) {
                        $origin = $waypoint['address'];
                    } elseif ($waypoint['destination']) {
                        $destination = $waypoint['address'];
                    }
                    $currentAd['waypoints'][] = [
                        'title' => $waypoint['address']['addressLocality'],
                        'destination' => $waypoint['destination'],
                        'latLng' => ['lat' => $waypoint['address']['latitude'], 'lon' => $waypoint['address']['longitude']],
                    ];
                }
                $searchLinkParams = [
                    'origin' => json_encode($origin),
                    'destination' => json_encode($destination),
                    'regular' => $isRegular,
                    'date' => $date,
                    'cid' => $community->getId(),
                ];
                $currentAd['searchLink'] = $this->router->generate('carpool_search_result_get', $searchLinkParams, UrlGeneratorInterface::ABSOLUTE_URL);
                $ways[] = $currentAd;
            }
        }

        return $ways;
    }

    /**
     * Get last accepted community users and format them.
     *
     * @param int Community's id
     *
     * @return null|array The last users formated
     */
    public function getLastUsers(int $communityId)
    {
        $lastUsersFormated = [];

        $this->dataProvider->setClass(Community::class);
        $this->dataProvider->setFormat(DataProvider::RETURN_JSON);
        $response = $this->dataProvider->getSpecialItem($communityId, 'lastUsers');
        $communityUsers = json_decode($response->getValue(), true);
        foreach ($communityUsers as $communityUser) {
            $acceptedDate = new \DateTime($communityUser['acceptedDate']);
            $lastUsersFormated[] = [
                'name' => ucfirst($communityUser['user']['givenName']).' '.$communityUser['user']['shortFamilyName'],
                'acceptedDate' => $acceptedDate->format('d/m/Y'),
            ];
        }

        return json_encode($lastUsersFormated);
    }

    public function communityMapsAds(int $id)
    {
        $this->dataProvider->setClass(Community::class);
        $this->dataProvider->setFormat(DataProvider::RETURN_JSON);
        $response = $this->dataProvider->getSpecialItem($id, 'mapsAds');
        $communities = json_decode($response->getValue(), true);
        if (isset($communities['mapsAds']) && is_array($communities['mapsAds'])) {
            foreach ($communities['mapsAds'] as &$mapsAd) {
                $date = (isset($mapsAd['outwardDate']) && !is_null($mapsAd['outwardDate'])) ? $mapsAd['outwardDate'] : null;
                $searchLinkParams = [
                    'origin' => json_encode($mapsAd['origin']),
                    'destination' => json_encode($mapsAd['destination']),
                    'regular' => $mapsAd['regular'],
                    'date' => $date,
                    'cid' => $mapsAd['entityId'],
                ];
                $mapsAd['searchLink'] = $this->router->generate('carpool_search_result_get', $searchLinkParams, UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return json_encode($communities);
    }

    public function communityMembers(int $id, array $params = [])
    {
        $this->dataProvider->setClass(Community::class);
        $this->dataProvider->setFormat(DataProvider::RETURN_JSON);
        $response = $this->dataProvider->getSpecialItem($id, 'members', $params);

        return $response->getValue();
    }

    // Refactor start

    /**
     * Get all communities available for registration.
     */
    public function getCommunityListForRegistration(?string $email = null)
    {
        $this->dataProvider->setClass(MCommunity::class);

        $params = [
            'userEmail' => $email,
            'page' => 1,
            'perPage' => 1000,
        ];

        if (count($this->territoryFilter) > 0) {
            $params['territory'] = $this->territoryFilter;
        }

        $response = $this->dataProvider->getCollection($params);

        return $response->getValue()->getMember();
    }

    /**
     * Get all relay points map for the community.
     */
    public function getRelayPointsMap(int $communityId)
    {
        $this->dataProvider->setClass(RelayPointMap::class);
        $response = $this->dataProvider->getCollection(['communityId' => $communityId]);

        return $response->getValue()->getMember();
    }
}
