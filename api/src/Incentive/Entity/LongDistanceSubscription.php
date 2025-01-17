<?php

namespace App\Incentive\Entity;

use App\DataProvider\Entity\MobConnect\Response\MobConnectSubscriptionResponse;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="mobconnect__long_distance_subscription")
 * @ORM\HasLifecycleCallbacks
 */
class LongDistanceSubscription
{
    public const INITIAL_COMMITMENT_PROOF_PATH = '/api/public/upload/eec-incentives/initial-commitment-proof';
    public const HONOUR_CERTIFICATE_PATH = '/api/public/upload/eec-incentives/long-distance-subscription/honour-certificate/';

    public const STATUS_REJECTED = 'VALIDEE';
    public const STATUS_VALIDATED = 'REJETEE';

    /**
     * @var int The user subscription ID
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var User The user
     *
     * @ORM\OneToOne(targetEntity="\App\User\Entity\User", inversedBy="longDistanceSubscription")
     * @ORM\JoinColumn(onDelete="SET NULL", unique=true)
     */
    private $user;

    /**
     * @var ArrayCollection The long distance log associated with the user
     *
     * @ORM\OneToMany(targetEntity="\App\Incentive\Entity\LongDistanceJourney", mappedBy="longDistanceSubscription", cascade={"persist"})
     */
    private $longDistanceJourneys;

    /**
     * @var string the ID of the mobConnect subscription
     *
     * @ORM\Column(type="string", length=255)
     */
    private $subscriptionId;

    /**
     * @var string the initial timestamp of the mobConnect subscription
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $initialTimestamp;

    /**
     * @var string the last timestamp of the mobConnect subscription
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $lastTimestamp;

    /**
     * @var string the status of the journey
     *
     * @ORM\Column(type="string", length=10, nullable=true)
     */
    private $status;

    /**
     * @var string the first name of the user
     *
     * @ORM\Column(type="string", length=255)
     */
    private $givenName;

    /**
     * @var string the family name of the user
     *
     * @ORM\Column(type="string", length=255)
     */
    private $familyName;

    /**
     * @var string the driving licence number of the user
     *
     * @ORM\Column(type="string", length=15)
     */
    private $drivingLicenceNumber;

    /**
     * @var string the full street address of the user
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $streetAddress;

    /**
     * @var string the address postal code of the user
     *
     * @ORM\Column(type="string", length=15, nullable=true)
     */
    private $postalCode;

    /**
     * @var string the address locality of the user
     *
     * @ORM\Column(type="string", length=100, nullable=true)
     */
    private $addressLocality;

    /**
     * @var string the telephone number of the user
     *
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $telephone;

    /**
     * @var string the email of the user
     *
     * @ORM\Column(type="string", length=255, unique=true)
     */
    private $email;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $createdAt;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true, options={"default"="CURRENT_TIMESTAMP"})
     */
    private $updatedAt;

    /**
     * The autogenerated initial commintment proof.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"comment": "The autogenerated initial commintment proof"})
     */
    private $initialCommitmentProof;

    /**
     * The autogenerated honour certificate.
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true, options={"comment": "The autogenerated honour certificate"})
     */
    private $honourCertificate;

    public function __construct(User $user, MobConnectSubscriptionResponse $mobConnectSubscriptionResponse)
    {
        $this->longDistanceJourneys = new ArrayCollection();

        $this->setUser($user);
        $this->setSubscriptionId($mobConnectSubscriptionResponse->getId());
        $this->setInitialTimestamp($mobConnectSubscriptionResponse->getTimestamp());

        $this->setGivenName($user->getGivenName());
        $this->setFamilyName($user->getFamilyName());
        $this->setDrivingLicenceNumber($user->getDrivingLicenceNumber());
        if (!is_null($user->getHomeAddress())) {
            $this->setStreetAddress($user->getHomeAddress()->getHouseNumber().' '.$user->getHomeAddress()->getStreetAddress());
            $this->setPostalCode($user->getHomeAddress()->getPostalCode());
            $this->setAddressLocality($user->getHomeAddress()->getAddressLocality());
        }
        $this->setTelephone($user->getTelephone());
        $this->setEmail($user->getEmail());
    }

    /**
     * @ORM\PrePersist
     */
    public function prePersist()
    {
        $this->createdAt = new \DateTime('now');
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function preUpdate()
    {
        $this->updatedAt = new \DateTime('now');
    }

    /**
     * Get the cee ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the user.
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User $user The user
     */
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function addLongDistanceJourney(LongDistanceJourney $longDistanceJourney): self
    {
        $this->longDistanceSubscriptions[] = $longDistanceJourney;
        $longDistanceJourney->setLongDistanceSubscription($this);

        return $this;
    }

    public function removeLongDistanceJourney(LongDistanceJourney $longDistanceJourney)
    {
        return $this->longDistanceSubscription->removeElement($longDistanceJourney);
    }

    public function getLongDistanceJourneys()
    {
        return $this->longDistanceJourneys;
    }

    /**
     * Get the ID of the mobConnect subscription.
     */
    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    /**
     * Set the ID of the mobConnect subscription.
     *
     * @param string $subscriptionId the ID of the mobConnect subscription
     */
    public function setSubscriptionId(string $subscriptionId): self
    {
        $this->subscriptionId = $subscriptionId;

        return $this;
    }

    /**
     * Get the initial timestamp of the mobConnect subscription.
     */
    public function getInitialTimestamp(): ?string
    {
        return $this->initialTimestamp;
    }

    /**
     * Set the initial timestamp of the mobConnect subscription.
     *
     * @param string $initialTimestamp the initial timestamp of the mobConnect subscription
     */
    public function setInitialTimestamp(?string $initialTimestamp): self
    {
        $this->initialTimestamp = $initialTimestamp;

        return $this;
    }

    /**
     * Get the last timestamp of the mobConnect subscription.
     *
     * @return string
     */
    public function getLastTimestamp(): ?string
    {
        return $this->lastTimestamp;
    }

    /**
     * Set the last timestamp of the mobConnect subscription.
     *
     * @param string $lastTimestamp the last timestamp of the mobConnect subscription
     */
    public function setLastTimestamp(?string $lastTimestamp): self
    {
        $this->lastTimestamp = $lastTimestamp;

        return $this;
    }

    /**
     * Get the status of the journey.
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Set the status of the journey.
     *
     * @param string $status the status of the journey
     */
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Get the first name of the user.
     */
    public function getGivenName(): string
    {
        return $this->givenName;
    }

    /**
     * Set the first name of the user.
     */
    public function setGivenName(string $givenName): self
    {
        $this->givenName = $givenName;

        return $this;
    }

    /**
     * Get the family name of the user.
     */
    public function getFamilyName(): string
    {
        return $this->familyName;
    }

    /**
     * Set the family name of the user.
     */
    public function setFamilyName(string $familyName): self
    {
        $this->familyName = $familyName;

        return $this;
    }

    /**
     * Get the driving licence number of the user.
     */
    public function getDrivingLicenceNumber(): string
    {
        return $this->drivingLicenceNumber;
    }

    /**
     * Set the driving licence number of the user.
     */
    public function setDrivingLicenceNumber(string $drivingLicenceNumber): self
    {
        $this->drivingLicenceNumber = $drivingLicenceNumber;

        return $this;
    }

    /**
     * Get the full street address of the user.
     */
    public function getStreetAddress(): string
    {
        return $this->streetAddress;
    }

    /**
     * Set the full street address of the user.
     */
    public function setStreetAddress(string $streetAddress): self
    {
        $this->streetAddress = $streetAddress;

        return $this;
    }

    /**
     * Get the address postal code of the user.
     */
    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    /**
     * Set the address postal code of the user.
     *
     * @param string $postalCode the address postal code of the user
     */
    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    /**
     * Get the address locality of the user.
     */
    public function getAddressLocality(): string
    {
        return $this->addressLocality;
    }

    /**
     * Set the address locality of the user.
     *
     * @param string $addressLocality the address locality of the user
     */
    public function setAddressLocality(string $addressLocality): self
    {
        $this->addressLocality = $addressLocality;

        return $this;
    }

    /**
     * Get the telephone number of the user.
     */
    public function getTelephone(): string
    {
        return $this->telephone;
    }

    /**
     * Set the telephone number of the user.
     */
    public function setTelephone(string $telephone): self
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * Get the email of the user.
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Set the email of the user.
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get the value of createdAt.
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Get the value of updatedAt.
     */
    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    /**
     * Get the autogenerated initial commintment proof.
     */
    public function getInitialCommitmentProof(): ?string
    {
        return self::INITIAL_COMMITMENT_PROOF_PATH.$this->initialCommitmentProof;
    }

    /**
     * Set the autogenerated initial commintment proof.
     *
     * @param string $initialCommitmentProof the autogenerated initial commintment proof
     */
    public function setInitialCommitmentProof(string $initialCommitmentProof): self
    {
        $this->initialCommitmentProof = $initialCommitmentProof;

        return $this;
    }

    /**
     * Get the autogenerated honour certificate.
     *
     * @return string
     */
    public function getHonourCertificate(): ?string
    {
        return self::HONOUR_CERTIFICATE_PATH.$this->honourCertificate;
    }

    /**
     * Set the autogenerated honour certificate.
     *
     * @param string $honourCertificate the autogenerated honour certificate
     */
    public function setHonourCertificate(string $honourCertificate): self
    {
        $this->honourCertificate = $honourCertificate;

        return $this;
    }
}
