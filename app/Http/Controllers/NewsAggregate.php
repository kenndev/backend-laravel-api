<?php

namespace App\Http\Controllers;

use App\Models\Settings;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class NewsAggregate extends Controller
{
    public function networkCalls($newYorkApi, $newsAPiOrg, $theGuradianApi)
    {
        $responses = Http::pool(fn(Pool $pool) => [
            $pool->as('first')->get($newYorkApi),
            $pool->as('second')->get($newsAPiOrg),
            $pool->as('third')->get($theGuradianApi),
        ]);

        $response['first_source'] = $responses['first']->json();
        $response['second_source'] = $responses['second']->json();
        $response['third_source'] = $responses['third']->json();

        return $response;
    }

    public function getNewsArticles()
    {
        $user = Auth::user();
        if (!empty($user)) {
            $settings = Settings::where('user_id', $user->id)->get();
        }
        if (empty($settings)) {
            $newYorkApi = 'https://api.nytimes.com/svc/search/v2/articlesearch.json?api-key=dcFUGBJ6J7ZdT2t4fP2XzhilBjaM0Pp3';
            $newsAPiOrg = 'https://newsapi.org/v2/everything?q=all&apiKey=1f5f2a716613403ba707b48af2fbabc1';
            $theGuradianApi = 'https://content.guardianapis.com/search?api-key=36fbbae3-c09a-433f-95f0-ebc58f4a3515&show-fields=thumbnail,trailText';
        } else {
            $cat = "";
            $news_org = "";
            $guardian = "";
            foreach ($settings as $key => $value) {
                if ($value->type == 'category') {
                    $cat = $cat . '"' . ucfirst($value->value) . '",';
                    $news_org = $news_org . strtolower($value->value) . ' AND ';
                    $guardian = $guardian . strtolower($value->value) . ',';
                }
            }
            $category_search = rtrim($cat, ',');
            $searchNewYorkQuery = 'fq=body:(' . $category_search . ')';
            $newYorkApi = 'https://api.nytimes.com/svc/search/v2/articlesearch.json?' . $searchNewYorkQuery . '&api-key=dcFUGBJ6J7ZdT2t4fP2XzhilBjaM0Pp3';
            $newsAPiOrg = 'https://newsapi.org/v2/everything?q=' . rtrim($news_org, ' AND ') . '&apiKey=1f5f2a716613403ba707b48af2fbabc1';
            $theGuradianApi = 'https://content.guardianapis.com/search?q=' . rtrim($guardian, ',') . '&api-key=36fbbae3-c09a-433f-95f0-ebc58f4a3515&show-fields=thumbnail,trailText';
        }
        $result = $this->networkCalls($newYorkApi, $newsAPiOrg, $theGuradianApi);
        $response = $this->processResults($result);
        return response()->json($response);
    }

    public function processResults($result)
    {
        $data = [];
        //NewsApi.org
        if (!empty($result['second_source'])) {
            foreach ($result['second_source']['articles'] as $key => $value) {
                $data[] = [
                    'id' => Str::random(40),
                    'source' => $value['source']['name'],
                    'title' => $value['title'],
                    'lead_paragraph' => $value['description'],
                    'web_url' => $value['url'],
                    'image' => isset($value['urlToImage']) ? $value['urlToImage'] : 'https://nbhc.ca/sites/default/files/styles/article/public/default_images/news-default-image%402x_0.png?itok=B4jML1jF',
                    'author' => $value['author'],
                    'published_at' => $value['publishedAt']
                ];
            }
        }

        //The Guardian
        if (!empty($result['third_source'])) {
            foreach ($result['third_source']['response']['results'] as $key => $value) {
                $data[] = [
                    'id' => Str::random(40),
                    'source' => 'The Guardian',
                    'title' => $value['webTitle'],
                    'lead_paragraph' => $value['fields']['trailText'],
                    'web_url' => $value['webUrl'],
                    'image' => isset($value['fields']['thumbnail']) ? $value['fields']['thumbnail'] : 'https://nbhc.ca/sites/default/files/styles/article/public/default_images/news-default-image%402x_0.png?itok=B4jML1jF',
                    'author' => "",
                    'published_at' => $value['webPublicationDate']
                ];
            }
        }
        //The Newyork times
        if (!empty($result['first_source'])) {
            foreach ($result['first_source']['response']['docs'] as $key => $value) {
                $multimedia = $value['multimedia'];
                $image = reset($multimedia);
                $name = !empty($image) ? 'https://www.nytimes.com/' . $image['url'] : 'https://nbhc.ca/sites/default/files/styles/article/public/default_images/news-default-image%402x_0.png?itok=B4jML1jF';
                $data[] = [
                    'id' => Str::random(40),
                    'source' => $value['source'],
                    'title' => $value['abstract'],
                    'lead_paragraph' => $value['lead_paragraph'],
                    'web_url' => $value['web_url'],
                    'image' => $name,
                    'author' => '',
                    'published_at' => $value['pub_date'],
                ];
            }
        }


        return $data;
    }

    public function saveSettings(Request $request)
    {

        $source = $request->input('source');
        $cat = $request->input('category');

        $user = $request->user();

        if (isset($cat)) {
            Settings::create([
                'type' => 'category',
                'value' => $cat,
                'user_id' => $user->id
            ]);
        }

        if (isset($source)) {
            Settings::create([
                'type' => 'source',
                'value' => $source,
                'user_id' => $user->id
            ]);
        }

        $settings = Settings::get();
        return response()->json($settings);
    }

    public function getSettings()
    {
        $settings = Settings::get();
        return response()->json($settings);
    }

    public function searchNews(Request $request)
    {
        $search = $request->input('search');
        $newYorkApi = 'https://api.nytimes.com/svc/search/v2/articlesearch.json?q=' . $search . '&api-key=dcFUGBJ6J7ZdT2t4fP2XzhilBjaM0Pp3';
        $newsAPiOrg = 'https://newsapi.org/v2/everything?q=' . $search . '&apiKey=1f5f2a716613403ba707b48af2fbabc1';
        $theGuradianApi = 'https://content.guardianapis.com/search?q=' . $search . '&api-key=36fbbae3-c09a-433f-95f0-ebc58f4a3515&show-fields=thumbnail,trailText';
        $result = $this->networkCalls($newYorkApi, $newsAPiOrg, $theGuradianApi);
        $response = $this->processResults($result);
        return response()->json($response);
    }

    public function footerNews()
    {
        $newsAPiOrg = 'https://newsapi.org/v2/everything?q=all&apiKey=1f5f2a716613403ba707b48af2fbabc1';
        $result = Http::get($newsAPiOrg);
        $response['second_source'] = $result->json();
        $res = $this->processResults($response);
        $finalRes = array_slice($res, 0, 3);
        return response()->json($finalRes);
    }
}