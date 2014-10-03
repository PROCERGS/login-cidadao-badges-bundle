<?php

namespace PROCERGS\LoginCidadao\BadgesBundle\Event;

use PROCERGS\LoginCidadao\BadgesControlBundle\Model\AbstractBadgesEventSubscriber;
use PROCERGS\LoginCidadao\BadgesControlBundle\Event\EvaluateBadgesEvent;
use PROCERGS\LoginCidadao\BadgesBundle\Model\Badge;
use Symfony\Component\Translation\TranslatorInterface;

class BadgesSubscriber extends AbstractBadgesEventSubscriber
{

    /** @var TranslatorInterface */
    protected $translator;
    
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
        
        $this->registerBadge('has_cpf', $translator->trans('has_cpf.description', array(), 'badges'));
        $this->registerBadge('valid_email', $translator->trans('valid_email.description', array(), 'badges'));
        $this->registerBadge('nfg_access_lvl', $translator->trans('nfg_access_lvl.description', array(), 'badges'));
        $this->registerBadge('voter_registration', $translator->trans('voter_registration.description', array(), 'badges'));
    }

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

    protected function checkCpf(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if (is_numeric($person->getCpf()) && strlen($person->getNfgAccessToken()) > 0) {
            $event->registerBadge($this->getBadge('has_cpf', true));
        }
    }

    protected function checkEmail(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if ($person->getEmailConfirmedAt() instanceof \DateTime && is_null($person->getConfirmationToken())) {
            $event->registerBadge($this->getBadge('valid_email', true));
        }
    }

    protected function checkNfg(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if ($person->getNfgProfile()) {

            $event->registerBadge($this->getBadge('nfg_access_lvl',
                                                  $person->getNfgProfile()->getAccessLvl()));

            if ($person->getNfgProfile()->getVoterRegistrationSit() > 0) {
                $event->registerBadge($this->getBadge('voter_registration', true));
            }
        }
    }

    protected function getBadge($name, $data)
    {
        if (array_key_exists($name, $this->getAvailableBadges())) {
            return new Badge($this->getName(), $name, $data);
        } else {
            throw new Exception("Badge $name not found in namespace {$this->getName()}.");
        }
    }

}
