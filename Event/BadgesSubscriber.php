<?php

namespace PROCERGS\LoginCidadao\BadgesBundle\Event;

use PROCERGS\LoginCidadao\BadgesControlBundle\Model\AbstractBadgesEventSubscriber;
use PROCERGS\LoginCidadao\BadgesControlBundle\Event\EvaluateBadgesEvent;
use PROCERGS\LoginCidadao\BadgesBundle\Model\Badge;

class BadgesSubscriber extends AbstractBadgesEventSubscriber
{

    public function onBadgeEvaluate(EvaluateBadgesEvent $event)
    {
        $this->checkCpf($event);
        $this->checkEmail($event);
        $this->checkNfg($event);
    }

    public function getName()
    {
        return 'login-cidadao';
    }

    public function getAvailableBadges()
    {
        return array();
    }

    protected function checkCpf(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if (is_numeric($person->getCpf()) && strlen($person->getNfgAccessToken()) > 0) {
            $badge = new Badge($this->getName(), 'has_cpf', true);
            $event->registerBadge($badge);
        }
    }

    protected function checkEmail(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if ($person->getEmailConfirmedAt() instanceof \DateTime && is_null($person->getConfirmationToken())) {
            $badge = new Badge($this->getName(), 'valid_email', true);
            $event->registerBadge($badge);
        }
    }

    protected function checkNfg(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if ($person->getNfgProfile()) {

            $event->registerBadge(new Badge($this->getName(), 'nfg_access_lvl',
                                            $person->getNfgProfile()->getAccessLvl()));

            if ($person->getNfgProfile()->getVoterRegistrationSit() > 0) {
                $event->registerBadge(new Badge($this->getName(),
                                                'voter_registration', true));
            }
        }
    }

}
