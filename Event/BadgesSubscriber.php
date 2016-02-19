<?php

namespace LoginCidadao\BadgesBundle\Event;

use LoginCidadao\BadgesControlBundle\Model\AbstractBadgesEventSubscriber;
use LoginCidadao\BadgesControlBundle\Event\EvaluateBadgesEvent;
use LoginCidadao\BadgesControlBundle\Event\ListBearersEvent;
use LoginCidadao\BadgesControlBundle\Model\BadgeInterface;
use LoginCidadao\BadgesBundle\Model\Badge;
use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManager;

class BadgesSubscriber extends AbstractBadgesEventSubscriber
{
    /** @var TranslatorInterface */
    protected $translator;

    /** @var EntityManager */
    protected $em;

    public function __construct(TranslatorInterface $translator,
                                EntityManager $em)
    {
        $this->translator = $translator;
        $this->em         = $em;

        $namespace = 'login-cidadao';
        $this->setName($namespace);

        $this->registerBadge('has_cpf',
            $translator->trans("$namespace.has_cpf.description", array(),
                'badges'), array('counter' => 'countHasCpf'));
        $this->registerBadge('valid_email',
            $translator->trans("$namespace.valid_email.description", array(),
                'badges'), array('counter' => 'countValidEmail'));
    }

    public function onBadgeEvaluate(EvaluateBadgesEvent $event)
    {
        $this->checkCpf($event);
        $this->checkEmail($event);
    }

    public function onListBearers(ListBearersEvent $event)
    {
        $filterBadge = $event->getBadge();
        if ($filterBadge instanceof BadgeInterface) {
            $countMethod = $this->badges[$filterBadge->getName()]['counter'];
            $count       = $this->{$countMethod}($filterBadge->getData());

            $event->setCount($filterBadge, $count);
        } else {
            foreach ($this->badges as $name => $badge) {
                $countMethod = $badge['counter'];
                $count       = $this->{$countMethod}();
                $badge       = new Badge($this->getName(), $name);

                $event->setCount($badge, $count);
            }
        }
    }

    protected function checkCpf(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if (is_numeric($person->getCpf())) {
            $event->registerBadge($this->getBadge('has_cpf', true));
        }
    }

    protected function checkEmail(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        if ($person->getEmailConfirmedAt() instanceof \DateTime && !$person->getConfirmationToken()) {
            $event->registerBadge($this->getBadge('valid_email', true));
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

    protected function countHasCpf()
    {
        return $this->em->getRepository('LoginCidadaoCoreBundle:Person')
                ->createQueryBuilder('p')
                ->select('COUNT(p)')
                ->andWhere('p.cpf IS NOT NULL')
                ->getQuery()->getSingleScalarResult();
    }

    protected function countValidEmail()
    {
        return $this->em->getRepository('LoginCidadaoCoreBundle:Person')
                ->createQueryBuilder('p')
                ->select('COUNT(p)')
                ->andWhere('p.confirmationToken IS NULL')
                ->andWhere('p.emailConfirmedAt IS NOT NULL')
                ->getQuery()->getSingleScalarResult();
    }
}
