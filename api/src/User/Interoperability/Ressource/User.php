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
 */

namespace App\User\Interoperability\Ressource;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Core\Annotation\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A User for Interoperability.
 *
 * @ApiResource(
 *      routePrefix="/interoperability",
 *      attributes={
 *          "force_eager"=false,
 *          "normalization_context"={"groups"={"readUser"}, "enable_max_depth"="true"},
 *          "denormalization_context"={"groups"={"writeUser"}}
 *      },
 *      collectionOperations={
 *          "interop_get"={
 *             "method"="GET",
 *             "security"="is_granted('reject',object)",
 *             "swagger_context" = {
 *               "summary"="Not permitted",
 *               "tags"={"Interoperability"}
 *             }
 *          },
 *          "interop_post"={
 *             "method"="POST",
 *             "security_post_denormalize"="is_granted('interop_user_create',object)",
 *             "swagger_context" = {
 *                  "summary"="Create a User created via interoperability. If a User with the same email already exists in our database, we will attache this account and not create a new one.",
 *                  "tags"={"Interoperability"},
 *                  "parameters" = {
 *                      {
 *                          "name" = "givenName",
 *                          "type" = "string",
 *                          "required" = true,
 *                          "description" = "User's given name!"
 *                      },
 *                      {
 *                          "name" = "familyName",
 *                          "type" = "string",
 *                          "required" = true,
 *                          "description" = "User's family name"
 *                      },
 *                      {
 *                          "name" = "email",
 *                          "type" = "string",
 *                          "required" = true,
 *                          "description" = "User's email"
 *                      },
 *                      {
 *                          "name" = "password",
 *                          "type" = "string",
 *                          "required" = true,
 *                          "description" = "Clear version of the password"
 *                      },
 *                      {
 *                          "name" = "gender",
 *                          "type" = "int",
 *                          "enum" = {1,2,3},
 *                          "required" = true,
 *                          "description" = "User's gender (1 : female, 2 : male, 3 : other)"
 *                      },
 *                      {
 *                          "name" = "newsSubscription",
 *                          "type" = "boolean",
 *                          "required" = false,
 *                          "description" = "News subscription"
 *                      },
 *                      {
 *                          "name" = "externalId",
 *                          "type" = "string",
 *                          "required" = false,
 *                          "description" = "External id of the user (the id used in the partner's system)"
 *                      },
 *                      {
 *                          "name" = "previouslyExisting",
 *                          "type" = "boolean",
 *                          "required" = false,
 *                          "description" = "ONLY GET - If the User has been attached to an already existing User not created by SSO"
 *                      },
 *                      {
 *                          "name" = "communityId",
 *                          "type" = "int",
 *                          "required" = false,
 *                          "description" = "The id of the community to associate to the user."
 *                      }
 *                  }
 *              }
 *          }
 *      },
 *      itemOperations={
 *          "interop_get"={
 *             "path"="/users/{id}",
 *             "method"="GET",
 *             "security"="is_granted('interop_user_read',object)",
 *             "swagger_context" = {
 *               "summary"="Get a User created via interoperability. You can only GET the Users that you created.",
 *               "tags"={"Interoperability"},
 *               "parameters" = {
 *                   {
 *                       "name" = "id",
 *                       "type" = "int",
 *                       "required" = true,
 *                       "description" = "User's id in our system"
 *                   }
 *               }
 *             }
 *          },
 *          "interop_put"={
 *             "path"="/users/{id}",
 *             "method"="PUT",
 *             "security"="is_granted('interop_user_update',object)",
 *             "swagger_context" = {
 *               "summary"="Update a User created via interoperability. You can only update the Users that you created.",
 *               "tags"={"Interoperability"}
 *             }
 *          }
 *      }
 * )
 *
 * @author Maxime Bardot <maxime.bardot@mobicoop.org>
 */
class User
{
    public const DEFAULT_ID = 999999999999;

    public const GENDER_FEMALE = 1;
    public const GENDER_MALE = 2;
    public const GENDER_OTHER = 3;

    public const GENDERS = [
        self::GENDER_FEMALE,
        self::GENDER_MALE,
        self::GENDER_OTHER,
    ];

    /**
     * @var int The id of this User
     *
     * @ApiProperty(identifier=true)
     * @Groups({"readUser","writeUser"})
     */
    private $id;

    /**
     * @var null|string the first name of the user
     *
     * @Assert\NotBlank
     * @Groups({"readUser","writeUser"})
     */
    private $givenName;

    /**
     * @var null|string the family name of the user
     *
     * @Assert\NotBlank
     * @Groups({"readUser","writeUser"})
     */
    private $familyName;

    /**
     * @var string the email of the user
     *
     * @Assert\Email()
     * @Groups({"readUser","writeUser"})
     */
    private $email;

    /**
     * @var null|\DateTimeInterface The birth date of the user
     *
     * @Groups({"readUser","writeUser"})
     */
    private $birthDate;

    /**
     * @var null|string The telephone number of the user
     *
     * @Groups({"readUser","writeUser"})
     */
    private $telephone;

    /**
     * @var string the encoded password of the user
     *
     * @Groups({"writeUser"})
     */
    private $password;

    /**
     * @var null|int The gender of the user (1=female, 2=male, 3=nc)
     *
     * @Assert\NotBlank
     * @Groups({"readUser","writeUser"})
     */
    private $gender;

    /**
     * @var null|bool the user accepts to receive news about the platform
     *
     * @Groups({"readUser","writeUser"})
     */
    private $newsSubscription;

    /**
     * @var int The external id of this User
     *
     * @Groups({"readUser","writeUser"})
     */
    private $externalId;

    /**
     * @var bool If the User has been attached to an already existing User not created by SSO
     *
     * @Groups({"readUser"})
     */
    private $previouslyExisting;

    /**
     * @var null|int
     *
     * @Groups({"writeUser"})
     */
    private $communityId;

    public function __construct(int $id = null)
    {
        if (!is_null($id)) {
            $this->id = $id;
        } else {
            $this->id = self::DEFAULT_ID;
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getGivenName(): ?string
    {
        return $this->givenName;
    }

    public function setGivenName(?string $givenName): self
    {
        $this->givenName = $givenName;

        return $this;
    }

    public function getFamilyName(): ?string
    {
        return $this->familyName;
    }

    public function setFamilyName(?string $familyName): self
    {
        $this->familyName = $familyName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): self
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getGender()
    {
        return $this->gender;
    }

    public function setGender($gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function hasNewsSubscription(): ?bool
    {
        return $this->newsSubscription;
    }

    public function setNewsSubscription(?bool $newsSubscription): self
    {
        $this->newsSubscription = $newsSubscription;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function isPreviouslyExisting(): ?bool
    {
        return $this->previouslyExisting;
    }

    public function setPreviouslyExisting(?bool $previouslyExisting): self
    {
        $this->previouslyExisting = $previouslyExisting;

        return $this;
    }

    public function getCommunityId(): ?int
    {
        return $this->communityId;
    }

    public function setCommunityId(?int $communityId): self
    {
        $this->communityId = $communityId;

        return $this;
    }
}
