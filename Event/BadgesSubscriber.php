<?php

namespace PROCERGS\LoginCidadao\BadgesBundle\Event;

use PROCERGS\LoginCidadao\BadgesControlBundle\Model\AbstractBadgesEventSubscriber;
use PROCERGS\LoginCidadao\BadgesControlBundle\Event\EvaluateBadgesEvent;
use PROCERGS\LoginCidadao\BadgesBundle\Model\Badge;

class BadgesSubscriber extends AbstractBadgesEventSubscriber
{

    public function onBadgeEvaluate(EvaluateBadgesEvent $event)
    {
        // Magic here
    }

    public function getName()
    {
        return '';
    }

    public function getAvailableBadges()
    {
        return array();
    }

}
