<?php

namespace LoginCidadao\BadgesBundle\Event;

use LoginCidadao\BadgesControlBundle\Model\AbstractBadgesEventSubscriber;
use LoginCidadao\BadgesControlBundle\Event\EvaluateBadgesEvent;
use LoginCidadao\BadgesControlBundle\Event\ListBearersEvent;
use LoginCidadao\BadgesBundle\Model\Badge;
use Symfony\Component\Translation\TranslatorInterface;
use Doctrine\ORM\EntityManager;
use LoginCidadao\BadgesControlBundle\Model\BadgeInterface;

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
        $this->em = $em;

        $namespace = 'login-cidadao';
        $this->setName($namespace);

        $this->registerBadge('has_cpf',
                             $translator->trans("$namespace.has_cpf.description",
                                                array(), 'badges'),
                                                array('counter' => 'countHasCpf'));
        $this->registerBadge('valid_email',
                             $translator->trans("$namespace.valid_email.description",
                                                array(), 'badges'),
                                                array('counter' => 'countValidEmail'));
        $this->registerBadge('nfg_access_lvl',
                             $translator->trans("$namespace.nfg_access_lvl.description",
                                                array(), 'badges'),
                                                array('counter' => 'countNfgAccessLvl'));
        $this->registerBadge('voter_registration',
                             $translator->trans("$namespace.voter_registration.description",
                                                array(), 'badges'),
                                                array('counter' => 'countVoterRegistration'));
    }

    public function onBadgeEvaluate(EvaluateBadgesEvent $event)
    {
        $this->checkCpf($event);
        $this->checkEmail($event);
        $this->checkNfg($event);
    }

    public function onListBearers(ListBearersEvent $event)
    {
        $filterBadge = $event->getBadge();
        if ($filterBadge instanceof BadgeInterface) {
            $countMethod = $this->badges[$filterBadge->getName()]['counter'];
            $count = $this->{$countMethod}($filterBadge->getData());

            $event->setCount($filterBadge, $count);
        } else {
            foreach ($this->badges as $name => $badge) {
                $countMethod = $badge['counter'];
                $count = $this->{$countMethod}();
                $badge = new Badge($this->getName(), $name);

                $event->setCount($badge, $count);
            }
        }
    }

    protected function checkCpf(EvaluateBadgesEvent $event)
    {
        $person = $event->getPerson();
        $nfg    = !method_exists($person, 'getNfgAccessToken') ||
            (strlen($person->getNfgAccessToken()) > 0);
        if (is_numeric($person->getCpf()) && $nfg) {
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
        if (method_exists($person, 'getNfgProfile') && $person->getNfgProfile()) {
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

    protected function countHasCpf()
    {
        return $this->em->getRepository('LoginCidadaoCoreBundle:Person')
                ->createQueryBuilder('p')
                ->select('COUNT(p)')
                ->andWhere('p.cpf IS NOT NULL')
                ->andWhere('p.nfgAccessToken IS NOT NULL')
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

    protected function countNfgAccessLvl($filterData = null)
    {
        $empty = array(
            "1" => 0,
            "2" => 0,
            "3" => 0
        );

        $query = $this->em->getRepository('LoginCidadaoCoreBundle:Person')
            ->createQueryBuilder('p')
            ->select('n.accessLvl, COUNT(n) total')
            ->join('LoginCidadaoCoreBundle:NfgProfile', 'n', 'WITH',
                   'p.nfgProfile = n')
            ->groupBy('n.accessLvl');

        if ($filterData !== null) {
            $query->andWhere('n.accessLvl = :filterData')
                ->setParameters(compact('filterData'));
        }

        $count = $query->getQuery()->getResult();
        if (!empty($count)) {
            $original = $count;
            $count = $empty;
            foreach ($original as $line) {
                $level = $line['accessLvl'];
                $count[$level] = $line['total'];
            }
            return $count;
        } else {
            return $empty;
        }
    }

    protected function countVoterRegistration()
    {
        return $this->em->getRepository('LoginCidadaoCoreBundle:Person')
                ->createQueryBuilder('p')
                ->select('COUNT(p)')
                ->join('LoginCidadaoCoreBundle:NfgProfile', 'n', 'WITH',
                       'p.nfgProfile = n')
                ->andWhere('n.voterRegistrationSit > 0')
                ->getQuery()->getSingleScalarResult();
    }

}
