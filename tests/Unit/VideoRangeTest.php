<?php

namespace Tests\Unit;

use App\Http\Controllers\CourseController;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class VideoRangeTest extends TestCase
{
    public function test_first_video_range_is_limited_to_one_megabyte(): void
    {
        $this->assertSame([0, 1048575, true], $this->range('bytes=0-', 50 * 1024 * 1024));
    }

    public function test_later_video_ranges_are_limited_to_eight_megabytes(): void
    {
        $start = 1048576;
        $this->assertSame([$start, 9437183, true], $this->range("bytes={$start}-", 50 * 1024 * 1024));
    }

    private function range(string $header, int $size): array
    {
        $request = Request::create('/video', 'GET', server: ['HTTP_RANGE' => $header]);
        $method = new ReflectionMethod(CourseController::class, 'requestedVideoRange');

        return $method->invoke(new CourseController, $request, $size);
    }
}
