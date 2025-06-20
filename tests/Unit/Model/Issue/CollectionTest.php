<?php
declare(strict_types=1);

namespace PerformanceReview\Tests\Unit\Model\Issue;

use PHPUnit\Framework\TestCase;
use PerformanceReview\Model\Issue\Collection;
use PerformanceReview\Model\IssueFactory;
use PerformanceReview\Model\Issue;

class CollectionTest extends TestCase
{
    /**
     * @var IssueFactory
     */
    private $issueFactory;
    
    /**
     * @var Collection
     */
    private $collection;
    
    protected function setUp(): void
    {
        $this->issueFactory = $this->createMock(IssueFactory::class);
        $this->collection = new Collection($this->issueFactory);
    }
    
    public function testCreateIssueReturnsIssueBuilder()
    {
        $builder = $this->collection->createIssue();
        
        $this->assertInstanceOf(
            \PerformanceReview\Model\Issue\IssueBuilder::class,
            $builder
        );
    }
    
    public function testAddIssue()
    {
        $issue = $this->createMock(\PerformanceReview\Model\IssueInterface::class);
        
        $this->collection->addIssue($issue);
        
        $this->assertCount(1, $this->collection->getIssues());
        $this->assertSame($issue, $this->collection->getIssues()[0]);
    }
    
    public function testGetIssuesReturnsEmptyArrayInitially()
    {
        $this->assertSame([], $this->collection->getIssues());
    }
    
    public function testCountReturnsCorrectNumber()
    {
        $issue1 = $this->createMock(\PerformanceReview\Model\IssueInterface::class);
        $issue2 = $this->createMock(\PerformanceReview\Model\IssueInterface::class);
        
        $this->collection->addIssue($issue1);
        $this->collection->addIssue($issue2);
        
        $this->assertEquals(2, $this->collection->count());
    }
    
    public function testHasIssuesReturnsFalseWhenEmpty()
    {
        $this->assertFalse($this->collection->hasIssues());
    }
    
    public function testHasIssuesReturnsTrueWhenNotEmpty()
    {
        $issue = $this->createMock(\PerformanceReview\Model\IssueInterface::class);
        $this->collection->addIssue($issue);
        
        $this->assertTrue($this->collection->hasIssues());
    }
    
    public function testFluentIssueCreation()
    {
        $issueData = [
            'priority' => 'high',
            'category' => 'Test',
            'issue' => 'Test issue',
            'details' => 'Test details',
            'current_value' => 'current',
            'recommended_value' => 'recommended'
        ];
        
        $issue = new Issue($issueData);
        
        $this->issueFactory
            ->expects($this->once())
            ->method('create')
            ->with($issueData)
            ->willReturn($issue);
        
        $this->collection->createIssue()
            ->setPriority('high')
            ->setCategory('Test')
            ->setIssue('Test issue')
            ->setDetails('Test details')
            ->setCurrentValue('current')
            ->setRecommendedValue('recommended')
            ->add();
        
        $this->assertCount(1, $this->collection->getIssues());
        $this->assertSame($issue, $this->collection->getIssues()[0]);
    }
}