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

namespace App\User\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Auth\Entity\AuthItem;
use Doctrine\ORM\QueryBuilder;
use App\Solidary\Entity\Solidary;

final class SolidaryFilter extends AbstractContextAwareFilter
{
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        if ($property != "solidary") {
            return;
        }

        // we will create a new querybuilder for retrieving the solidary users, to avoid modifying the one used for the original query
        $em = $queryBuilder->getEntityManager();

        $queryBuilder
        ->leftJoin('u.userAuthAssignments', 'uaa')
        ->leftJoin('uaa.authItem', 'ai')
        ->andWhere('ai.id in ('.AuthItem::ROLE_SOLIDARY_BENEFICIARY.','.AuthItem::ROLE_SOLIDARY_VOLUNTEER.')');

        return;
    }

    // This function is only used to hook in documentation generators (supported by Swagger and Hydra)
    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $strategy) {
            $description["$property"] = [
                'property' => $property,
                'type' => 'number',
                'format' => 'integer',
                'required' => false,
                'swagger' => [
                    'description' => 'Filter on users that have a solidary record',
                    'name' => 'solidary',
                    'type' => 'integer',
                ],
            ];
        }

        return $description;
    }
}
