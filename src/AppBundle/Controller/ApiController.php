<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\Criteria;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ApiController extends Controller {

	/**
	 * Get the description of all the packs as an array of JSON objects.
	 * 
	 * @ApiDoc(
	 *  section="Pack",
	 *  resource=true,
	 *  description="All the Packs",
	 *  parameters={
	 *    {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 * )
	 * @param Request $request
	 * @return Response
	 */
	public function listPacksAction(Request $request) {
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(array('Access-Control-Allow-Origin' => '*'));

		$jsonp = $request->query->get('jsonp');

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

        /* @var $list_packs \AppBundle\Entity\Pack[] */
		$list_packs = $em->getRepository('AppBundle:Pack')->findBy(array(), array("dateRelease" => "ASC", "position" => "ASC"));

		// check the last-modified-since header
		$lastModified = NULL;
		foreach($list_packs as $pack) {
			if (!$lastModified || $lastModified < $pack->getDateUpdate()) {
				$lastModified = $pack->getDateUpdate();
			}
		}

		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		$packs = array();

		/* @var $pack \AppBundle\Entity\Pack */
		foreach($list_packs as $pack) {
			$real = count($pack->getCards());
			$max = $pack->getSize();
			$packs[] = array(
				"name" => $pack->getName(),
				"code" => $pack->getCode(),
				"position" => $pack->getPosition(),
				"cycle_position" => $pack->getCycle()->getPosition(),
				"available" => $pack->getDateRelease() ? $pack->getDateRelease()->format('Y-m-d') : '',
				"known" => intval($real),
				"total" => $max,
				"url" => $this->get('router')->generate('cards_list', array('pack_code' => $pack->getCode()), UrlGeneratorInterface::ABSOLUTE_URL),
				"id" => $pack->getId()
			);
		}

		$content = json_encode($packs);
		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);

		return $response;
	}

	/**
	 * Get the description of a card as a JSON object.
	 *
	 * @ApiDoc(
	 *  section="Card",
	 *  resource=true,
	 *  description="One Card",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
	 *      {
	 *          "name"="card_code",
	 *          "dataType"="string",
	 *          "description"="The code of the card to get, e.g. '01001'"
	 *      },
	 *      {
	 *          "name"="_format",
	 *          "dataType"="string",
	 *          "requirement"="json",
	 *          "description"="The format of the returned data. Only 'json' is supported at the moment."
	 *      }
	 *  },
	 * )
	 * @param Request $request
     * @return Response
	 */
	public function getCardAction($card_code, Request $request) {
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(['Access-Control-Allow-Origin' => '*']);

		$jsonp = $request->query->get('jsonp');

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

        /* @var $card \AppBundle\Entity\Card */
        $card = $em->getRepository('AppBundle:Card')->findOneBy(["code" => $card_code]);

		// check the last-modified-since header
		$lastModified = null;
		if (!$lastModified || $lastModified < $card->getDateUpdate()) {
			$lastModified = $card->getDateUpdate();
		}

		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		// build the response
		/* @var $card \AppBundle\Entity\Card */
		$card = $this->get('cards_data')->getCardInfo($card, true, "en");

		$content = json_encode($card);
		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);

		return $response;
	}

	/**
	 * Get the description of all the cards as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Card",
	 *  resource=true,
	 *  description="All the Cards",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 * )
	 * @param Request $request
     * @return Response
	 */
	public function listCardsAction(Request $request) {
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(['Access-Control-Allow-Origin' => '*']);

		$jsonp = $request->query->get('jsonp');

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

        /* @var $list_cards \AppBundle\Entity\Card[] */
        $list_cards = $em->getRepository('AppBundle:Card')->findBy([], ["code" => "ASC"]);

		// check the last-modified-since header
		$lastModified = null;
		/* @var $card \AppBundle\Entity\Card */
		foreach ($list_cards as $card) {
			if (!$lastModified || $lastModified < $card->getDateUpdate()) {
				$lastModified = $card->getDateUpdate();
			}
		}

		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		// build the response

		$cards = [];
		/* @var $card \AppBundle\Entity\Card */
		foreach ($list_cards as $card) {
			$cards[] = $this->get('cards_data')->getCardInfo($card, true, "en");
		}

		$content = json_encode($cards);
		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);

		return $response;
	}

	/**
	 * Get the description of all the card from a pack, as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Card",
	 *  resource=true,
	 *  description="All the Cards from One Pack",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
	 *      {
	 *          "name"="pack_code",
	 *          "dataType"="string",
	 *          "description"="The code of the pack to get the cards from, e.g. 'core'"
	 *      },
	 *      {
	 *          "name"="_format",
	 *          "dataType"="string",
	 *          "requirement"="json|xml|xlsx|xls",
	 *          "description"="The format of the returned data. Only 'json' is supported at the moment."
	 *      }
	 *  },
	 * )
	 * @param Request $request
     * @return Response
	 */
	public function listCardsByPackAction($pack_code, Request $request) {
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(['Access-Control-Allow-Origin' => '*']);

		$jsonp = $request->query->get('jsonp');

		$format = $request->getRequestFormat();
		if ($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');

			return $response;
		}

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

        /* @var $pack \AppBundle\Entity\Pack */
		$pack = $em->getRepository('AppBundle:Pack')->findOneBy(['code' => $pack_code]);
		if (!$pack) {
			die();
		}

		$conditions = $this->get('cards_data')->syntax("e:$pack_code");
		$this->get('cards_data')->validateConditions($conditions);
		$query = $this->get('cards_data')->buildQueryFromConditions($conditions);

		$cards = [];
		$last_modified = null;

        /* @var $rows \AppBundle\Entity\Card[] */
		if ($query && $rows = $this->get('cards_data')->get_search_rows($conditions, "set")) {
			for ($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				if (empty($last_modified) || $last_modified < $rows[$rowindex]->getDateUpdate()) {
					$last_modified = $rows[$rowindex]->getDateUpdate();
				}
			}
			$response->setLastModified($last_modified);
			if ($response->isNotModified($request)) {
				return $response;
			}
			for ($rowindex = 0; $rowindex < count($rows); $rowindex++) {
				$card = $this->get('cards_data')->getCardInfo($rows[$rowindex], true, "en");
				$cards[] = $card;
			}
		}

		$content = json_encode($cards);
		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);

		return $response;
	}

	/**
	 * Get the description of a decklist as a JSON object.
	 *
	 * @ApiDoc(
	 *  section="Decklist",
	 *  resource=true,
	 *  description="One Decklist",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
	 *      {
	 *          "name"="decklist_id",
	 *          "dataType"="integer",
	 *          "requirement"="\d+",
	 *          "description"="The numeric identifier of the decklist"
	 *      },
	 *      {
	 *          "name"="_format",
	 *          "dataType"="string",
	 *          "requirement"="json",
	 *          "description"="The format of the returned data. Only 'json' is supported at the moment."
	 *      }
	 *  },
	 * )
	 * @param Request $request
     * @return Response
	 */
	public function getDecklistAction($decklist_id, Request $request) {
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(['Access-Control-Allow-Origin' => '*']);

		$jsonp = $request->query->get('jsonp');

		$format = $request->getRequestFormat();
		if ($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');

			return $response;
		}

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

        /* @var $decklist \AppBundle\Entity\Decklist */
		$decklist = $em->getRepository('AppBundle:Decklist')->find($decklist_id);
		if (!$decklist) {
			die();
		}

		$response->setLastModified($decklist->getDateUpdate());
		if ($response->isNotModified($request)) {
			return $response;
		}

		$content = json_encode($decklist);

		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}

		$response->setContent($content);

		return $response;
	}

	/**
	 * Get the description of all the decklists published at a given date, as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Decklist",
	 *  resource=true,
	 *  description="All the Decklists from One Day",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
	 *      {
	 *          "name"="date",
	 *          "dataType"="string",
	 *          "requirement"="\d\d\d\d-\d\d-\d\d",
	 *          "description"="The date, format 'Y-m-d'"
	 *      },
	 *      {
	 *          "name"="_format",
	 *          "dataType"="string",
	 *          "requirement"="json",
	 *          "description"="The format of the returned data. Only 'json' is supported at the moment."
	 *      }
	 *  },
	 * )
	 * @param Request $request
     * @return Response
	 */
	public function listDecklistsByDateAction($date, Request $request) {
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(['Access-Control-Allow-Origin' => '*']);

		$jsonp = $request->query->get('jsonp');

		$format = $request->getRequestFormat();
		if ($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');

			return $response;
		}

		$start = \DateTime::createFromFormat('Y-m-d', $date);
		$start->setTime(0, 0, 0);
		$end = clone $start;
		$end->add(new \DateInterval("P1D"));

		$expr = Criteria::expr();
		$criteria = Criteria::create();
		$criteria->where($expr->gte('dateCreation', $start));
		$criteria->andWhere($expr->lt('dateCreation', $end));

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

        /* @var $decklists \Doctrine\Common\Collections\ArrayCollection */
		$decklists = $em->getRepository('AppBundle:Decklist')->matching($criteria);
		if (!$decklists) {
			die();
		}

		$dateUpdates = $decklists->map(function($decklist) {
            /* @var $decklist \AppBundle\Entity\Decklist */
			return $decklist->getDateUpdate();
		})->toArray();

		$response->setLastModified(max($dateUpdates));
		if ($response->isNotModified($request)) {
			return $response;
		}

		$content = json_encode($decklists);

		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}

		$response->setContent($content);

		return $response;
	}

	/**
	 * Get the top 10 decklists published containing given card, as an array of JSON objects.
	 *
	 * @ApiDoc(
	 *  section="Decklist",
	 *  resource=true,
	 *  description="Top 10 Decklists containing a specific card",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
	 *      {
	 *          "name"="card_code",
	 *          "dataType"="string",
	 *          "description"="The code of the card to get, e.g. '01001'"
	 *      },
	 *      {
	 *          "name"="_format",
	 *          "dataType"="string",
	 *          "requirement"="json",
	 *          "description"="The format of the returned data. Only 'json' is supported at the moment."
	 *      }
	 *  },
	 * )
	 * @param Request $request
     * @return Response
	 */
	public function listTopDecklistsByCardAction($card_code, Request $request) {
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(['Access-Control-Allow-Origin' => '*']);

		$jsonp = $request->query->get('jsonp');

		$format = $request->getRequestFormat();
		if ($format !== 'json') {
			$response->setContent($request->getRequestFormat() . ' format not supported. Only json is supported.');

			return $response;
		}

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

		$card = $this->getDoctrine()->getRepository('AppBundle:Card')->findOneBy(['code' => $card_code]);
		if (!$card) {
			$response->setContent('[]');

			return $response;
		}

		$qb = $em->createQueryBuilder();
		// Select decklists
		$qb->select('d.id, d.name, d.nameCanonical, d.dateCreation, d.dateUpdate');
		$qb->from('AppBundle:Decklist', 'd');

		// high popularity
		$qb->addSelect('(1+d.nbVotes)/(1+POWER(DATE_DIFF(CURRENT_TIMESTAMP(), d.dateCreation), 2)) AS HIDDEN popularity');
		$qb->orderBy('popularity', 'DESC');

		// containing the card
		$qb->innerJoin('d.slots', "s");
		$qb->andWhere("s.card = :card");
		$qb->setParameter("card", $card);

		// limit 10
		$qb->setMaxResults(10);

		$query = $qb->getQuery();

        /* @var $decklists \Doctrine\Common\Collections\ArrayCollection */
        $decklists = $query->getArrayResult();

        $lastModified = NULL;
        foreach($decklists as &$decklist) {
            if (!$lastModified || $lastModified < $decklist['dateUpdate']) {
                $lastModified = $decklist['dateUpdate'];
            }
        }

        $response->setLastModified($lastModified);
        if ($response->isNotModified($request)) {
            return $response;
        }

        foreach($decklists as &$decklist) {
            $decklist['url'] = $this->generateUrl('decklist_detail', [
                'decklist_id' => $decklist['id'],
                'decklist_name' => $decklist['nameCanonical']
            ]);
            unset($decklist['descriptionMd']);
            unset($decklist['descriptionHtml']);

            $decklist['dateCreation'] = $decklist['dateCreation']->format('c');
            $decklist['dateUpdate'] = $decklist['dateUpdate']->format('c');
        }

        $content = json_encode($decklists);

		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}

		$response->setContent($content);

		return $response;
	}


	/**
	 * Get the description of a scenario as a JSON object.
	 *
	 * @ApiDoc(
	 *  section="Scenario",
	 *  resource=true,
	 *  description="One Scenario",
	 *  parameters={
	 *      {"name"="jsonp", "dataType"="string", "required"=false, "description"="JSONP callback"}
	 *  },
	 *  requirements={
	 *      {
	 *          "name"="scenario_id",
	 *          "dataType"="integer",
	 *          "description"="The code of the scenario to get, e.g. '01001'"
	 *      },
	 *      {
	 *          "name"="_format",
	 *          "dataType"="string",
	 *          "requirement"="json",
	 *          "description"="The format of the returned data. Only 'json' is supported at the moment."
	 *      }
	 *  },
	 * )
	 * @param Request $request
     * @return Response
	 */
	public function getScenarioAction($scenario_id, Request $request) {
		$response = new Response();
		$response->setPublic();
		$response->setMaxAge($this->container->getParameter('cache_expiration'));
		$response->headers->add(['Access-Control-Allow-Origin' => '*']);

		$jsonp = $request->query->get('jsonp');

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getDoctrine()->getManager();

        /* @var $scenario \AppBundle\Entity\Scenario */
        $scenario = $em->getRepository('AppBundle:Scenario')->findOneBy(['id' => $scenario_id]);

		// check the last-modified-since header
		$lastModified = null;
		if (!$lastModified || $lastModified < $scenario->getDateUpdate()) {
			$lastModified = $scenario->getDateUpdate();
		}

		$response->setLastModified($lastModified);
		if ($response->isNotModified($request)) {
			return $response;
		}

		$content = json_encode($scenario);
		if (isset($jsonp)) {
			$content = "$jsonp($content)";
			$response->headers->set('Content-Type', 'application/javascript');
		} else {
			$response->headers->set('Content-Type', 'application/json');
		}
		$response->setContent($content);

		return $response;
	}

}
