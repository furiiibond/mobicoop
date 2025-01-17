<?php

namespace App\Incentive\Service;

use App\Carpool\Entity\CarpoolProof;
use App\DataProvider\Entity\MobConnect\MobConnectApiProvider;
use App\DataProvider\Entity\OpenIdSsoProvider;
use App\DataProvider\Ressource\MobConnectApiParams;
use App\Incentive\Entity\Flat\LongDistanceSubscription as FlatLongDistanceSubscription;
use App\Incentive\Entity\Flat\ShortDistanceSubscription as FlatShortDistanceSubscription;
use App\Incentive\Entity\LongDistanceJourney;
use App\Incentive\Entity\LongDistanceSubscription;
use App\Incentive\Entity\MobConnectAuth;
use App\Incentive\Entity\ShortDistanceJourney;
use App\Incentive\Entity\ShortDistanceSubscription;
use App\Incentive\Event\FirstLongDistanceJourneyValidatedEvent;
use App\Incentive\Event\FirstShortDistanceJourneyValidatedEvent;
use App\Incentive\Event\LastLongDistanceJourneyValidatedEvent;
use App\Incentive\Event\LongDistanceSubscriptionClosedEvent;
use App\Incentive\Event\ShortDistanceSubscriptionClosedEvent;
use App\Incentive\Resource\CeeSubscriptions;
use App\Payment\Entity\CarpoolItem;
use App\Payment\Entity\CarpoolPayment;
use App\User\Entity\SsoUser;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Subscription Management Manager.
 *
 * @author Olivier Fillol <olivier.fillol@mobicoop.org>
 */
class MobConnectSubscriptionManager
{
    /**
     * @var EntityManagerInterface
     */
    private $_em;

    /**
     * @var EventDispatcherInterface
     */
    private $_eventDispatcher;

    /**
     * @var LoggerInterface
     */
    private $_logger;

    /**
     * @var MobConnectApiProvider
     */
    private $_mobConnectApiProvider;

    /**
     * @var array
     */
    private $_mobConnectParams;

    /**
     * @var array
     */
    private $_ssoServices;

    /**
     * The authenticated user.
     *
     * @var User
     */
    private $_user;

    private $_userSubscription;

    private $_ceeSubscription;
    private $_ceeEligibleProofs;

    public function __construct(
        EntityManagerInterface $em,
        Security $security,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        array $ssoServices,
        array $mobConnectParams
    ) {
        $this->_em = $em;
        $this->_eventDispatcher = $eventDispatcher;
        $this->_logger = $logger;

        $this->_user = $security->getUser();

        $this->_ssoServices = $ssoServices;
        $this->_mobConnectParams = $mobConnectParams;
        $this->_ceeEligibleProofs = [];
    }

    private function __createAuth(User $user, SsoUser $ssoUser)
    {
        $mobConnectAuth = new MobConnectAuth($user, $ssoUser);

        $this->_user->setMobConnectAuth($mobConnectAuth);

        $this->_em->persist($mobConnectAuth);
        $this->_em->flush();
    }

    private function __updateAuth(SsoUser $ssoUser)
    {
        $mobConnectAuth = $this->_user->getMobConnectAuth();

        $mobConnectAuth->setAccessToken($ssoUser->getAccessToken());
        $mobConnectAuth->setAccessTokenExpiresDate($ssoUser->getAccessTokenExpiresDuration());
        $mobConnectAuth->setRefreshToken($ssoUser->getRefreshToken());
        $mobConnectAuth->setRefreshTokenExpiresDate($ssoUser->getRefreshTokenExpiresDuration());

        $this->_em->flush();
    }

    private function __getCarpoolersNumber(int $askId): int
    {
        $conn = $this->_em->getConnection();

        $sql = 'SELECT DISTINCT ci.debtor_user_id FROM carpool_item ci WHERE ci.ask_id = '.$askId.'';

        $stmt = $conn->prepare($sql);
        $stmt->execute();

        return count($stmt->fetchAll(\PDO::FETCH_COLUMN)) + 1;
    }

    private function __getFlatJourneys($journeys): array
    {
        $subscriptions = [];

        foreach ($journeys as $journey) {
            if ($journey instanceof ShortDistanceJourney) {
                array_push($subscriptions, new FlatShortDistanceSubscription($journey));
            } else {
                array_push($subscriptions, new FlatLongDistanceSubscription($journey));
            }
        }

        return $subscriptions;
    }

    private function __getRpcJourneyId(int $id): string
    {
        return 'Mobicoop_'.$id;
    }

    private function __getSubscriptionId(): string
    {
        return $this->_userSubscription->getSubscriptionId();
    }

    private function __isValidParameters(): bool
    {
        return
                !empty($this->_ssoServices)
                && array_key_exists(MobConnectApiProvider::SERVICE_NAME, $this->_ssoServices)

            && (
                !empty($this->_mobConnectParams)
                && (
                    array_key_exists('api_uri', $this->_mobConnectParams)
                    && !is_null($this->_mobConnectParams['api_uri'])
                    && !empty($this->_mobConnectParams['api_uri'])
                )
                && (
                    array_key_exists('credentials', $this->_mobConnectParams)
                    && is_array($this->_mobConnectParams['credentials'])
                    && !empty($this->_mobConnectParams['credentials'])
                    && array_key_exists('client_id', $this->_mobConnectParams['credentials'])
                    && !empty($this->_mobConnectParams['credentials']['client_id'])
                    && array_key_exists('api_key', $this->_mobConnectParams['credentials'])
                )
                && (
                    array_key_exists('subscription_ids', $this->_mobConnectParams)
                    && is_array($this->_mobConnectParams['subscription_ids'])
                    && !empty($this->_mobConnectParams['subscription_ids'])
                    && array_key_exists('short_distance', $this->_mobConnectParams['subscription_ids'])
                    && !empty($this->_mobConnectParams['subscription_ids']['short_distance'])
                    && array_key_exists('long_distance', $this->_mobConnectParams['subscription_ids'])
                    && !empty($this->_mobConnectParams['subscription_ids']['long_distance'])
                )
            )
        ;
    }

    private function __setApiProviderParams()
    {
        $this->_mobConnectApiProvider = new MobConnectApiProvider($this->_em, new MobConnectApiParams($this->_mobConnectParams), $this->_user, $this->_ssoServices);
    }

    private function __verifySubscription()
    {
        $response = $this->_mobConnectApiProvider->verifyUserSubscription($this->__getSubscriptionId());

        $this->_userSubscription->setStatus($response->getStatus());
        $this->_userSubscription->setLastTimestamp($response->getTimestamp());
    }

    /**
     * Keep only the eligible proofs (for short distance only).
     */
    private function __getCEEEligibleProofsShortDistance(User $user)
    {
        foreach ($user->getCarpoolProofsAsDriver() as $proof) {
            if (!is_null($proof->getAsk()) && $proof->getAsk()->getMatching()->getCommonDistance() >= CeeSubscriptions::LONG_DISTANCE_MINIMUM_IN_METERS) {
                continue;
            }

            if (CarpoolProof::TYPE_HIGH !== $proof->getType() && CarpoolProof::TYPE_UNDETERMINED_DYNAMIC !== $proof->getType()) {
                continue;
            }

            $this->_ceeEligibleProofs[] = $proof;
        }
    }

    private function __computeShortDistance(User $user)
    {
        $this->__getCEEEligibleProofsShortDistance($user);
        foreach ($this->_ceeEligibleProofs as $proof) {
            switch ($proof->getStatus()) {
                case CarpoolProof::STATUS_PENDING:
                case CarpoolProof::STATUS_SENT:$this->_ceeSubscription->setNbPendingProofs($this->_ceeSubscription->getNbPendingProofs() + 1);

                    break;

                case CarpoolProof::STATUS_ERROR:
                case CarpoolProof::STATUS_ACQUISITION_ERROR:
                case CarpoolProof::STATUS_NORMALIZATION_ERROR:
                case CarpoolProof::STATUS_FRAUD_ERROR:$this->_ceeSubscription->setNbRejectedProofs($this->_ceeSubscription->getNbRejectedProofs() + 1);

                    break;

                case CarpoolProof::STATUS_VALIDATED:$this->_ceeSubscription->setNbValidatedProofs($this->_ceeSubscription->getNbValidatedProofs() + 1);

                    break;
            }
        }
    }

    // * PUBLIC FUNCTIONS ---------------------------------------------------------------------------------------------------------------------------

    public function updateAuth(User $user, SsoUser $ssoUser)
    {
        $this->_user = $user;

        if (is_null($this->_user->getMobConnectAuth())) {
            $this->__createAuth($this->_user, $ssoUser);
        } else {
            $this->__updateAuth($ssoUser);
        }

        $this->_em->flush();
    }

    /**
     * For the authenticated user, if needed, creates the CEE sheets.
     */
    public function createSubscriptions(User $user)
    {
        if (!$this->__isValidParameters()) {
            return;
        }

        $this->_user = $user;

        if (is_null($this->_user->getShortDistanceSubscription())) {
            $shortDistanceSubscription = $this->createShortDistanceSubscription();
            // TODO: #5359 -> $shortDistanceSubscription->setInitialCommitmentProof($initialCommitmentproof);

            $this->_em->persist($shortDistanceSubscription);
        }

        if (is_null($this->_user->getLongDistanceSubscription())) {
            $longDistanceSubscription = $this->createLongDistanceSubscription();
            // TODO: #5359 -> $longDistanceSubscription->setInitialCommitmentProof($initialCommitmentproof);

            $this->_em->persist($longDistanceSubscription);
        }

        $this->_em->flush();
    }

    public function createShortDistanceSubscription()
    {
        $this->__setApiProviderParams();

        if (is_null($this->_user->getShortDistanceSubscription()) && CeeJourneyService::isUserAccountReadyForShortDistanceSubscription($this->_user, $this->_logger)) {
            $mobConnectShortDistanceSubscription = $this->_mobConnectApiProvider->postSubscriptionForShortDistance();

            return new ShortDistanceSubscription($this->_user, $mobConnectShortDistanceSubscription);
        }
    }

    public function createLongDistanceSubscription()
    {
        $this->__setApiProviderParams();

        if (is_null($this->_user->getLongDistanceSubscription()) && CeeJourneyService::isUserAccountReadyForLongDistanceSubscription($this->_user, $this->_logger)) {
            $mobConnectLongDistanceSubscription = $this->_mobConnectApiProvider->postSubscriptionForLongDistance();

            return new LongDistanceSubscription($this->_user, $mobConnectLongDistanceSubscription);
        }
    }

    /**
     * Returns flat paths to be used in particular as logs.
     * This service is called by the CeeSubscriptionsCollectionDataProvider.
     */
    public function getUserSubscriptions(User $user)
    {
        $this->_ceeSubscription = new CeeSubscriptions($this->_user->getId());

        if (!is_null($user->getShortDistanceSubscription())) {
            $shortDistanceSubscriptions = $this->__getFlatJourneys($user->getShortDistanceSubscription()->getShortDistanceJourneys());
            $this->_ceeSubscription->setShortDistanceSubscriptions($shortDistanceSubscriptions);
        }

        if (!is_null($user->getLongDistanceSubscription())) {
            $longDistanceSubscriptions = $this->__getFlatJourneys($user->getLongDistanceSubscription()->getLongDistanceJourneys());

            $this->_ceeSubscription->setLongDistanceSubscriptions($longDistanceSubscriptions);
        }

        $this->__computeShortDistance($user);

        return [$this->_ceeSubscription];
    }

    /**
     * Updates subscriptions (long or short distance) based on provided carpoolProof.
     */
    public function updateSubscription(CarpoolProof $carpoolProof, CarpoolPayment $carpoolPayment = null): void
    {
        if (!$this->__isValidParameters()) {
            return;
        }

        if (is_null($this->_user)) {
            $this->_user = $carpoolProof->getDriver();
        }

        $journeyDate = $carpoolProof->getAsk()->getCriteria()->getFromDate();

        switch (true) {
            case CeeJourneyService::isValidLongDistanceJourney($carpoolProof, $this->_logger):
                $this->_userSubscription = $this->_user->getLongDistanceSubscription();

                if (
                    is_null($this->_userSubscription)
                    || CeeJourneyService::isDateExpired($journeyDate->add(new \DateInterval('P'.CeeJourneyService::REFERENCE_TIME_LIMIT.'M')))
                    || CeeJourneyService::LONG_DISTANCE_TRIP_THRESHOLD < count($this->_userSubscription->getLongDistanceJourneys())
                ) {
                    return;
                }
                $journey = new LongDistanceJourney(
                    $carpoolPayment,
                    $carpoolProof,
                    $this->__getCarpoolersNumber($carpoolProof->getAsk()->getId())
                );

                $this->_userSubscription->addLongDistanceJourney($journey);

                break;

            case CeeJourneyService::isValidShortDistanceJourney($carpoolProof, $this->_logger):
                $this->_userSubscription = $this->_user->getShortDistanceSubscription();

                if (
                    is_null($this->_userSubscription)
                    || !CeeJourneyService::isDateAfterReferenceDate($journeyDate)
                    || CeeJourneyService::SHORT_DISTANCE_TRIP_THRESHOLD <= count($this->_userSubscription->getShortDistanceJourneys())
                ) {
                    return;
                }

                $journey = new ShortDistanceJourney(
                    $carpoolProof,
                    $this->__getCarpoolersNumber($carpoolProof->getAsk()->getId()),
                    $this->__getRpcJourneyId($carpoolProof->getId()),
                    CeeJourneyService::RPC_NUMBER_STATUS
                );

                $this->_userSubscription->addShortDistanceJourney($journey);

                break;
        }

        $paymentDate = !is_null($carpoolPayment) && !is_null($carpoolPayment->getUpdatedDate()) ? $carpoolPayment->getUpdatedDate() : null;

        if ($this->_userSubscription) {
            $this->__setApiProviderParams();

            switch (true) {
                case $this->_userSubscription instanceof LongDistanceSubscription:
                    switch (count($this->_userSubscription->getLongDistanceJourneys())) {
                        case CeeJourneyService::LOW_THRESHOLD_PROOF:
                            // The journey is added to the EEC sheet
                            if (is_null($paymentDate)) {
                                throw new \LogicException(MobConnectMessages::PAYMENT_DATE_MISSING);
                            }

                            $this->_mobConnectApiProvider->patchUserSubscription($this->__getSubscriptionId(), null, false, $paymentDate);

                            // TODO: #5359 we generate the long distance honour certificate
                            // TODO: #5359 $this->_userSubscription->setHonourCertificate($honourCertificate);

                            $event = new FirstLongDistanceJourneyValidatedEvent($journey);
                            $this->_eventDispatcher->dispatch(FirstLongDistanceJourneyValidatedEvent::NAME, $event);

                            $this->__verifySubscription();

                            if (LongDistanceSubscription::STATUS_VALIDATED === $this->_userSubscription->getStatus()) {
                                $event = new LastLongDistanceJourneyValidatedEvent($journey);
                                $this->_eventDispatcher->dispatch(LastLongDistanceJourneyValidatedEvent::NAME, $event);
                            }

                            break;

                        case CeeJourneyService::LONG_DISTANCE_TRIP_THRESHOLD:
                            $event = new LongDistanceSubscriptionClosedEvent($this->_userSubscription);
                            $this->_eventDispatcher->dispatch(LongDistanceSubscriptionClosedEvent::NAME, $event);

                            break;
                    }

                    break;

                case $this->_userSubscription instanceof ShortDistanceSubscription:
                    switch (count($this->_userSubscription->getShortDistanceJourneys())) {
                        case CeeJourneyService::LOW_THRESHOLD_PROOF:
                            // The journey is added to the EEC sheet
                            $this->_mobConnectApiProvider->patchUserSubscription($this->__getSubscriptionId(), $this->__getRpcJourneyId($carpoolProof->getId()), true);

                            // TODO: #5359 we generate the short distance honour certificate
                            // TODO: #5359 $this->_userSubscription->setHonourCertificate($honourCertificate);

                            $this->__verifySubscription();

                            if (ShortDistanceSubscription::STATUS_VALIDATED === $this->_userSubscription->getStatus()) {
                                $journey->setBonusStatus(ShortDistanceJourney::BONUS_STATUS_PENDING);

                                $event = new FirstShortDistanceJourneyValidatedEvent($journey);
                                $this->_eventDispatcher->dispatch(FirstShortDistanceJourneyValidatedEvent::NAME, $event);
                            }

                            break;

                        case CeeJourneyService::SHORT_DISTANCE_TRIP_THRESHOLD:
                            $journey->setBonusStatus(ShortDistanceJourney::BONUS_STATUS_PENDING);

                            $event = new ShortDistanceSubscriptionClosedEvent($this->_userSubscription);
                            $this->_eventDispatcher->dispatch(ShortDistanceSubscriptionClosedEvent::NAME, $event);

                            break;
                    }

                    break;
            }

            $this->_em->flush();
        }
    }

    /**
     * Updates long distance subscription after a payment has been validated.
     */
    public function updateLongDistanceSubscriptionAfterPayment(CarpoolPayment $carpoolPayment): void
    {
        if (!$this->__isValidParameters()) {
            return;
        }

        // Array of carpoolItem where driver is associated with MobConnect
        $filteredCarpoolItems = array_filter($carpoolPayment->getCarpoolItems(), function (CarpoolItem $carpoolItem) {
            $driver = $carpoolItem->getCreditorUser();

            return
                !is_null($driver->getSsoId())
                && !is_null($driver->getSsoProvider())
                && OpenIdSsoProvider::SSO_PROVIDER_MOBCONNECT === $driver->getSsoProvider()
            ;
        });

        foreach ($filteredCarpoolItems as $carpoolItem) {
            $driver = $carpoolItem->getCreditorUser();

            // Array of carpoolProof where driver is the carpoolItem driver
            $filteredCarpoolProofs = array_filter($carpoolItem->getAsk()->getCarpoolProofs(), function (CarpoolProof $carpoolProof) use ($driver) {
                return $carpoolProof->getDriver() === $driver;
            });

            foreach ($filteredCarpoolProofs as $carpool) {
                $this->updateSubscription($carpool, $carpoolPayment);
            }
        }
    }
}
