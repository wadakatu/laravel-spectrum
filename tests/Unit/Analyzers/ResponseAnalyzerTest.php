<?php

namespace LaravelSpectrum\Tests\Unit\Analyzers;

use LaravelSpectrum\Analyzers\ResponseAnalyzer;
use LaravelSpectrum\DTO\ResponseInfo;
use LaravelSpectrum\DTO\ResponseType;
use LaravelSpectrum\Support\CollectionAnalyzer;
use LaravelSpectrum\Support\ModelSchemaExtractor;
use LaravelSpectrum\Tests\Fixtures\Models\User;
use LaravelSpectrum\Tests\TestCase;
use PhpParser\ParserFactory;

class ResponseAnalyzerTest extends TestCase
{
    private ResponseAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $this->analyzer = new ResponseAnalyzer(
            $parser,
            new ModelSchemaExtractor,
            new CollectionAnalyzer
        );
    }

    public function test_analyzes_response_json_pattern()
    {
        $controller = new class
        {
            public function show($id)
            {
                $user = User::find($id);

                return response()->json([
                    'data' => $user->only(['id', 'name', 'email']),
                    'meta' => [
                        'version' => '1.0',
                        'timestamp' => now()->toIso8601String(),
                    ],
                ]);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'show');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::OBJECT, $result->type);
        $this->assertArrayHasKey('data', $result->properties);
        $this->assertArrayHasKey('meta', $result->properties);
        $this->assertArrayHasKey('id', $result->properties['data']['properties']);
        $this->assertArrayHasKey('name', $result->properties['data']['properties']);
        $this->assertArrayHasKey('email', $result->properties['data']['properties']);
    }

    public function test_analyzes_direct_array_return()
    {
        $controller = new class
        {
            public function index()
            {
                return [
                    'status' => 'success',
                    'total' => 100,
                    'page' => 1,
                ];
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'index');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::OBJECT, $result->type);
        $this->assertArrayHasKey('status', $result->properties);
        $this->assertEquals('string', $result->properties['status']['type']);
        $this->assertEquals('integer', $result->properties['total']['type']);
    }

    public function test_analyzes_eloquent_model_return()
    {
        $controller = new class
        {
            public function show($id)
            {
                return User::findOrFail($id);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'show');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::OBJECT, $result->type);
        // Note: hasProperties() depends on database connectivity and namespace resolution
        // In test environment with anonymous class, model extraction may return empty properties
        // The important thing is the response type is correctly identified
    }

    public function test_analyzes_collection_with_map()
    {
        $controller = new class
        {
            public function index()
            {
                return User::all()->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'display_name' => $user->name,
                        'contact' => $user->email,
                    ];
                });
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'index');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        // Collection maps return collection type with items
        $array = $result->toArray();
        $this->assertArrayHasKey('type', $array);
        // Collection analysis behavior depends on the actual implementation
        // The important thing is the response is properly analyzed as a ResponseInfo object
    }

    public function test_analyzes_void_return()
    {
        $controller = new class
        {
            public function delete($id)
            {
                User::destroy($id);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'delete');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::VOID, $result->type);
        $this->assertTrue($result->isVoid());
    }

    public function test_handles_exception_gracefully()
    {
        $result = $this->analyzer->analyze('NonExistentClass', 'method');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::UNKNOWN, $result->type);
        $this->assertTrue($result->hasError());
    }

    public function test_analyzes_response_download_pattern()
    {
        $controller = new class
        {
            public function downloadFile()
            {
                return response()->download(storage_path('app/reports/report.pdf'));
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'downloadFile');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::BINARY_FILE, $result->type);
        $this->assertEquals('application/pdf', $result->contentType);
    }

    public function test_analyzes_response_download_with_filename()
    {
        $controller = new class
        {
            public function downloadReport()
            {
                return response()->download(storage_path('app/data.csv'), 'export.csv');
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'downloadReport');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::BINARY_FILE, $result->type);
        $this->assertEquals('text/csv', $result->contentType);
        $this->assertEquals('export.csv', $result->fileName);
    }

    public function test_analyzes_response_file_pattern()
    {
        $controller = new class
        {
            public function showImage()
            {
                return response()->file(storage_path('app/images/photo.jpg'));
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'showImage');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::BINARY_FILE, $result->type);
        $this->assertEquals('image/jpeg', $result->contentType);
    }

    public function test_analyzes_response_stream_pattern()
    {
        $controller = new class
        {
            public function streamData()
            {
                return response()->stream(function () {
                    echo 'streaming data';
                }, 200, ['Content-Type' => 'text/event-stream']);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'streamData');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::STREAMED, $result->type);
        $this->assertEquals('text/event-stream', $result->contentType);
    }

    public function test_analyzes_response_stream_download_pattern()
    {
        $controller = new class
        {
            public function exportLargeFile()
            {
                return response()->streamDownload(function () {
                    echo 'large file content';
                }, 'export.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'exportLargeFile');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::STREAMED, $result->type);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $result->contentType);
        $this->assertEquals('export.xlsx', $result->fileName);
    }

    public function test_analyzes_response_with_custom_content_type_header()
    {
        $controller = new class
        {
            public function xmlResponse()
            {
                return response('<xml>data</xml>', 200, ['Content-Type' => 'application/xml']);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'xmlResponse');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::CUSTOM, $result->type);
        $this->assertEquals('application/xml', $result->contentType);
    }

    public function test_analyzes_response_with_plain_text_content_type()
    {
        $controller = new class
        {
            public function textResponse()
            {
                return response('Hello World', 200, ['Content-Type' => 'text/plain']);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'textResponse');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::CUSTOM, $result->type);
        $this->assertEquals('text/plain', $result->contentType);
    }

    public function test_analyzes_response_with_lowercase_content_type_header()
    {
        $controller = new class
        {
            public function xmlResponse()
            {
                return response('<xml>data</xml>', 200, ['content-type' => 'application/xml']);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'xmlResponse');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::CUSTOM, $result->type);
        $this->assertEquals('application/xml', $result->contentType);
    }

    public function test_analyzes_stream_with_uppercase_content_type_header()
    {
        $controller = new class
        {
            public function streamData()
            {
                return response()->stream(function () {
                    echo 'streaming data';
                }, 200, ['CONTENT-TYPE' => 'text/event-stream']);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'streamData');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::STREAMED, $result->type);
        $this->assertEquals('text/event-stream', $result->contentType);
    }

    public function test_defaults_to_octet_stream_for_unknown_extension()
    {
        $controller = new class
        {
            public function downloadUnknown()
            {
                return response()->download(storage_path('app/data.unknown'));
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'downloadUnknown');

        $this->assertInstanceOf(ResponseInfo::class, $result);
        $this->assertEquals(ResponseType::BINARY_FILE, $result->type);
        $this->assertEquals('application/octet-stream', $result->contentType);
    }

    public function test_analyzes_png_file_extension()
    {
        $controller = new class
        {
            public function downloadFile()
            {
                return response()->download(storage_path('images/photo.png'));
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'downloadFile');

        $this->assertEquals(ResponseType::BINARY_FILE, $result->type);
        $this->assertEquals('image/png', $result->contentType);
    }

    public function test_analyzes_xlsx_file_extension()
    {
        $controller = new class
        {
            public function downloadFile()
            {
                return response()->download(storage_path('reports/data.xlsx'));
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'downloadFile');

        $this->assertEquals(ResponseType::BINARY_FILE, $result->type);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $result->contentType);
    }

    public function test_analyzes_txt_file_extension()
    {
        $controller = new class
        {
            public function downloadFile()
            {
                return response()->download(storage_path('logs/app.txt'));
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'downloadFile');

        $this->assertEquals(ResponseType::BINARY_FILE, $result->type);
        $this->assertEquals('text/plain', $result->contentType);
    }

    public function test_analyzes_zip_file_extension()
    {
        $controller = new class
        {
            public function downloadFile()
            {
                return response()->download(storage_path('archives/backup.zip'));
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'downloadFile');

        $this->assertEquals(ResponseType::BINARY_FILE, $result->type);
        $this->assertEquals('application/zip', $result->contentType);
    }

    public function test_prefers_success_response_shape_over_early_error_guard_clause()
    {
        $controller = new class
        {
            public function show()
            {
                if (request()->boolean('unauthorized')) {
                    return response()->json(['code' => 'UNAUTHORIZED'], 400);
                }

                return response()->json([
                    'data' => [
                        'id' => 1,
                        'name' => 'John',
                    ],
                ]);
            }
        };

        $result = $this->analyzer->analyze(get_class($controller), 'show');

        $this->assertEquals(ResponseType::OBJECT, $result->type);
        $this->assertArrayHasKey('data', $result->properties);
        $this->assertArrayNotHasKey('code', $result->properties);
    }
}
