<?php

namespace App\Http\Controllers;
use App\Show;
use App\ShowMeta;
use Aria2;
use Curl;
use Log;

class TorrentController extends Controller {

	public function findTorrent($name = '', $_id = '') {
		if ($name == '') {
			$name = @$_GET['name'];
			$_id = @$_GET['id'];
		}
		preg_match("/(s[0-9]e[0-9]+)/i", $name, $m);
		//  Serach Mod
		if (preg_match("/S\.H\.I\.E\.L\.D/", $name)) {
			$name = "Marvels.Agents.of.S.H.I.E.L.D " . @$m[1];
		}if (preg_match("/DC's/", $name)) {
			$name = "Legends of Tomorrow " . @$m[1];
		}
		$s = @$m[1];
		$ch = new Curl();
		$result = $ch->get('https://kickass.cd/usearch/' . $name . "/");
		$html = str_get_html($result);
		if (!$html) {
			return response()->json("Try Again!", 500);
		}
		$x = [];
		foreach ($html->find('tr') as $key => $value) {
			try {
				$_x = @$value->find('.cellMainLink')[0]->innertext;
				if (preg_match("/$s.*(ettv|hdtv)/i", $_x)) {
					$x = [@$value->find('.cellMainLink')[0]->innertext, @$value->find('a[title=Torrent magnet link]')[0]->href];
					$show = ShowMeta::find($_id);
					if ($show && strlen($x[1]) > 10) {
						$show->magnet = $x[1];
						$show->save();
						$this->startDownload($show->magnet);
					}
					return response()->json("Torrent Found!", 200);
				}
			} catch (Exception $e) {
			}
		}
		return response()->json("Try Again!", 500);
	}

	public function startDownload($uri) {
		$aria2 = new Aria2();
		$aria2->addUri([$uri], [
			'dir' => storage_path() . "/downloads",
			'--seed-time' => 0,
		]);
	}

	public function aria2status() {
		$aria2 = new Aria2();
		$r = $aria2->tellActive();
		return response()->json($r, 200);
	}

	public function aria2remove() {
		$aria2 = new Aria2();
		$aria2->remove(@$_GET['id']);
		return redirect('/');
	}

	public function updateAll() {
		$s = Show::all();
		foreach ($s as $key => $value) {
			file_get_contents(url('/add-show-ajax?id=' . $value->tvmaze_id));
		}
	}

	public function AutoDownload() {
		$shows = ShowMeta::where('schedule', date('Y-m-d', strtotime('-1 day')))
			->where('magnet', null)->get();
		foreach ($shows as $key => $show) {
			$name = sprintf("%s S%02dE%02d", $show->show->name, $show->season, $show->episode);
			Log::info($this->findTorrent($name, $show->id));
			sleep(5);
		}
	}
}
