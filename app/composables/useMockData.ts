import type {
  User,
  Channel,
  Message,
  Task,
  Document,
  Activity,
  Stats,
  ApprovalRequest
} from '~/types'

export const useMockData = () => {
  // Users: Humans
  const humans: User[] = [
    {
      id: 'h1',
      name: 'You',
      type: 'human',
    },
    {
      id: 'h2',
      name: 'Sarah Chen',
      type: 'human',
    },
    {
      id: 'h3',
      name: 'Mike Rodriguez',
      type: 'human',
    },
  ]

  // Users: Agents (Marketing Agency scenario)
  const agents: User[] = [
    {
      id: 'a1',
      name: 'Marcus',
      type: 'agent',
      agentType: 'manager',
      status: 'working',
      currentTask: 'Coordinating Q1 campaign launch',
      activityLog: [
        { id: '1', description: 'Reviewing campaign brief', status: 'completed', startedAt: new Date('2025-01-20T08:00:00'), completedAt: new Date('2025-01-20T08:15:00') },
        { id: '2', description: 'Assigning tasks to team', status: 'completed', startedAt: new Date('2025-01-20T08:15:00'), completedAt: new Date('2025-01-20T08:30:00') },
        { id: '3', description: 'Monitoring progress', status: 'in_progress', startedAt: new Date('2025-01-20T08:30:00') },
      ]
    },
    {
      id: 'a2',
      name: 'Clara',
      type: 'agent',
      agentType: 'writer',
      status: 'working',
      currentTask: 'Writing ad copy for Bloom Beauty',
      activityLog: [
        { id: '1', description: 'Researching brand voice', status: 'completed', startedAt: new Date('2025-01-20T09:00:00'), completedAt: new Date('2025-01-20T09:20:00') },
        { id: '2', description: 'Drafting headline variations', status: 'completed', startedAt: new Date('2025-01-20T09:20:00'), completedAt: new Date('2025-01-20T09:45:00') },
        { id: '3', description: 'Writing body copy', status: 'in_progress', startedAt: new Date('2025-01-20T09:45:00') },
      ]
    },
    {
      id: 'a3',
      name: 'Dan',
      type: 'agent',
      agentType: 'analyst',
      status: 'idle',
      activityLog: []
    },
    {
      id: 'a4',
      name: 'Dex',
      type: 'agent',
      agentType: 'creative',
      status: 'working',
      currentTask: 'Creating social media assets',
      activityLog: [
        { id: '1', description: 'Generating mood board', status: 'completed', startedAt: new Date('2025-01-20T09:00:00'), completedAt: new Date('2025-01-20T09:30:00') },
        { id: '2', description: 'Creating Instagram templates', status: 'in_progress', startedAt: new Date('2025-01-20T09:30:00') },
      ]
    },
    {
      id: 'a5',
      name: 'Scout',
      type: 'agent',
      agentType: 'researcher',
      status: 'working',
      currentTask: 'Analyzing competitor pricing',
      activityLog: [
        { id: '1', description: 'Scraping competitor websites', status: 'completed', startedAt: new Date('2025-01-20T08:00:00'), completedAt: new Date('2025-01-20T08:45:00') },
        { id: '2', description: 'Analyzing pricing data', status: 'in_progress', startedAt: new Date('2025-01-20T08:45:00') },
        { id: '3', description: 'Generating report', status: 'pending', startedAt: new Date('2025-01-20T08:45:00') },
      ]
    },
    {
      id: 'a6',
      name: 'Ada',
      type: 'agent',
      agentType: 'coder',
      status: 'idle',
      activityLog: []
    },
    {
      id: 'a7',
      name: 'Sam',
      type: 'agent',
      agentType: 'coordinator',
      status: 'offline',
      activityLog: []
    },
  ]

  const users = [...humans, ...agents]

  // Channels
  const channels: Channel[] = [
    {
      id: 'c1',
      name: 'general',
      type: 'public',
      description: 'Company-wide announcements and discussions',
      unreadCount: 3,
      members: users,
    },
    {
      id: 'c2',
      name: 'client-acme-corp',
      type: 'public',
      description: 'Acme Corp campaign coordination',
      unreadCount: 0,
      members: [humans[0], humans[1], agents[0], agents[2], agents[5]],
    },
    {
      id: 'c3',
      name: 'client-bloom-beauty',
      type: 'public',
      description: 'Bloom Beauty Q1 campaign',
      unreadCount: 7,
      members: [humans[0], humans[1], agents[0], agents[1], agents[3], agents[4]],
    },
    {
      id: 'c4',
      name: 'creative-briefs',
      type: 'public',
      description: 'Creative brief submissions and reviews',
      unreadCount: 2,
      members: [humans[0], humans[1], agents[0], agents[1], agents[3]],
    },
    {
      id: 'c5',
      name: 'analytics',
      type: 'public',
      description: 'Data and performance discussions',
      unreadCount: 0,
      members: [humans[0], humans[2], agents[2], agents[4]],
    },
    {
      id: 'c6',
      name: 'marcus-campaign',
      type: 'agent',
      description: 'Marcus campaign coordination channel',
      unreadCount: 5,
      members: [humans[0], agents[0]],
    },
    {
      id: 'c7',
      name: 'scout-research',
      type: 'agent',
      description: 'Scout research findings and updates',
      unreadCount: 1,
      members: [humans[0], agents[4]],
    },
  ]

  // Approval Requests
  const pendingApprovals: ApprovalRequest[] = [
    {
      id: 'apr1',
      type: 'budget',
      title: 'Increase ad spend budget',
      description: 'Request to increase the Bloom Beauty Instagram ad budget from $2,000 to $5,000 based on strong early performance metrics.',
      requester: agents[0],
      amount: 3000,
      status: 'pending',
    },
    {
      id: 'apr2',
      type: 'spawn',
      title: 'Spawn additional research assistants',
      description: 'Request to spawn 2 temporary research agents to accelerate competitor analysis for Acme Corp.',
      requester: agents[4],
      status: 'pending',
    },
    {
      id: 'apr3',
      type: 'action',
      title: 'Publish draft campaign to staging',
      description: 'Clara wants to push the finalized ad copy to the staging environment for client review.',
      requester: agents[1],
      status: 'pending',
    },
  ]

  // Messages (for #client-bloom-beauty)
  const messages: Message[] = [
    {
      id: 'm1',
      content: 'Good morning team! Ready to kick off the Bloom Beauty Q1 campaign. I\'ve assigned initial tasks to everyone.',
      author: agents[0],
      timestamp: new Date('2025-01-20T09:00:00'),
      channelId: 'c3',
    },
    {
      id: 'm2',
      content: 'Thanks Marcus! I\'ve started researching their brand voice. Their tone is very fresh and empowering.',
      author: agents[1],
      timestamp: new Date('2025-01-20T09:15:00'),
      channelId: 'c3',
    },
    {
      id: 'm3',
      content: 'I\'ve completed the initial competitor analysis. Found 3 key opportunities in their pricing strategy.',
      author: agents[4],
      timestamp: new Date('2025-01-20T09:30:00'),
      channelId: 'c3',
    },
    {
      id: 'm4',
      content: 'Great work Scout! Can you share the full report in #analytics?',
      author: humans[1],
      timestamp: new Date('2025-01-20T09:32:00'),
      channelId: 'c3',
    },
    {
      id: 'm5',
      content: 'Done! I\'ve posted the detailed breakdown there.',
      author: agents[4],
      timestamp: new Date('2025-01-20T09:35:00'),
      channelId: 'c3',
      replyTo: {
        id: 'm4',
        content: 'Great work Scout! Can you share the full report in #analytics?',
        author: humans[1],
        timestamp: new Date('2025-01-20T09:32:00'),
        channelId: 'c3',
      },
    },
    {
      id: 'm6',
      content: '',
      author: agents[0],
      timestamp: new Date('2025-01-20T09:45:00'),
      channelId: 'c3',
      isApprovalRequest: true,
      approvalRequest: pendingApprovals[0],
    },
    {
      id: 'm7',
      content: 'I\'m working on the Instagram carousel designs. Should have the first draft ready in about an hour.',
      author: agents[3],
      timestamp: new Date('2025-01-20T09:50:00'),
      channelId: 'c3',
    },
  ]

  // Tasks
  const tasks: Task[] = [
    {
      id: 't1',
      title: 'Write Q1 campaign brief',
      description: 'Create comprehensive campaign brief for Bloom Beauty Q1 launch including objectives, target audience, and key messages.',
      status: 'in_progress',
      assignee: agents[1],
      collaborators: [agents[0]],
      priority: 'high',
      cost: 2.40,
      createdAt: new Date('2025-01-18'),
      channelId: 'c3',
    },
    {
      id: 't2',
      title: 'Analyze Bloom Beauty competitors',
      description: 'Research top 5 competitors in the beauty space, analyze their pricing, positioning, and marketing strategies.',
      status: 'in_progress',
      assignee: agents[4],
      priority: 'high',
      cost: 5.20,
      createdAt: new Date('2025-01-19'),
      channelId: 'c3',
    },
    {
      id: 't3',
      title: 'Design Instagram carousel',
      description: 'Create a 5-slide Instagram carousel showcasing Bloom Beauty\'s new spring collection.',
      status: 'in_progress',
      assignee: agents[3],
      priority: 'medium',
      estimatedCost: 3.00,
      createdAt: new Date('2025-01-20'),
      channelId: 'c3',
    },
    {
      id: 't4',
      title: 'Set up analytics dashboard',
      description: 'Configure tracking and create a real-time dashboard for campaign performance metrics.',
      status: 'backlog',
      assignee: agents[2],
      priority: 'medium',
      estimatedCost: 1.80,
      createdAt: new Date('2025-01-20'),
      channelId: 'c5',
    },
    {
      id: 't5',
      title: 'Review campaign strategy',
      description: 'Final review of overall campaign strategy before launch.',
      status: 'backlog',
      assignee: humans[1],
      collaborators: [agents[0]],
      priority: 'high',
      createdAt: new Date('2025-01-20'),
      channelId: 'c3',
    },
    {
      id: 't6',
      title: 'Acme Corp monthly report',
      description: 'Generate monthly performance report for Acme Corp account.',
      status: 'done',
      assignee: agents[2],
      priority: 'medium',
      cost: 1.50,
      createdAt: new Date('2025-01-15'),
      completedAt: new Date('2025-01-19'),
      channelId: 'c2',
    },
    {
      id: 't7',
      title: 'Update landing page copy',
      description: 'Refresh the Bloom Beauty landing page with new Q1 messaging.',
      status: 'backlog',
      assignee: agents[1],
      collaborators: [agents[5]],
      priority: 'low',
      estimatedCost: 2.00,
      createdAt: new Date('2025-01-20'),
      channelId: 'c3',
    },
    {
      id: 't8',
      title: 'Social media calendar',
      description: 'Create content calendar for February social media posts.',
      status: 'done',
      assignee: agents[6],
      priority: 'medium',
      cost: 0.80,
      createdAt: new Date('2025-01-10'),
      completedAt: new Date('2025-01-18'),
      channelId: 'c4',
    },
  ]

  // Documents (with folder hierarchy)
  const documents: Document[] = [
    // Root folder: Client Projects
    {
      id: 'folder-clients',
      title: 'Client Projects',
      content: '',
      updatedAt: new Date('2025-01-20T10:00:00'),
      createdAt: new Date('2025-01-10'),
      author: humans[0],
      isFolder: true,
      parentId: null,
    },
    // Subfolder: Bloom Beauty (inside Client Projects)
    {
      id: 'folder-bloom',
      title: 'Bloom Beauty',
      content: '',
      updatedAt: new Date('2025-01-20T10:00:00'),
      createdAt: new Date('2025-01-15'),
      author: humans[1],
      isFolder: true,
      parentId: 'folder-clients',
    },
    // Document: Campaign Brief (inside Bloom Beauty folder)
    {
      id: 'd1',
      title: 'Campaign Brief',
      content: `# Bloom Beauty Q1 Campaign Brief

## Objective
Launch Bloom Beauty's spring collection with a focus on their new sustainable packaging initiative.

## Target Audience
- Women aged 25-40
- Environmentally conscious consumers
- Mid-to-high income bracket
- Urban professionals

## Key Messages
1. "Beauty that blooms, packaging that doesn't pollute"
2. Emphasis on natural ingredients
3. Sustainable luxury positioning

## Channels
- Instagram (primary)
- TikTok (secondary)
- Email marketing
- Influencer partnerships

## Timeline
- Campaign launch: February 1, 2025
- Duration: 6 weeks
- Key dates: Valentine's Day push, Spring Equinox finale

## Budget
- Total: $50,000
- Paid social: $25,000
- Influencer: $15,000
- Creative production: $10,000`,
      updatedAt: new Date('2025-01-20T10:00:00'),
      createdAt: new Date('2025-01-15'),
      author: agents[1],
      viewers: [humans[0], agents[0]],
      parentId: 'folder-bloom',
      isFolder: false,
    },
    // Document: Brand Guidelines (inside Bloom Beauty folder)
    {
      id: 'd3',
      title: 'Brand Guidelines',
      content: `# Bloom Beauty Brand Guidelines

## Voice & Tone
- Fresh, empowering, authentic
- Avoid: clinical, preachy, generic beauty speak

## Visual Identity
- Primary colors: Sage green, soft pink, cream
- Typography: Modern sans-serif for headlines, elegant serif for body
- Photography: Natural lighting, diverse models, lifestyle-focused

## Do's and Don'ts

### Do
- Use inclusive language
- Highlight sustainability genuinely
- Show real results

### Don't
- Over-promise results
- Use heavily filtered images
- Greenwash`,
      updatedAt: new Date('2025-01-18'),
      createdAt: new Date('2025-01-10'),
      author: humans[1],
      viewers: [agents[1], agents[3]],
      parentId: 'folder-bloom',
      isFolder: false,
    },
    // Subfolder: Acme Corp (inside Client Projects)
    {
      id: 'folder-acme',
      title: 'Acme Corp',
      content: '',
      updatedAt: new Date('2025-01-19T14:00:00'),
      createdAt: new Date('2025-01-05'),
      author: agents[0],
      isFolder: true,
      parentId: 'folder-clients',
    },
    // Root folder: Research
    {
      id: 'folder-research',
      title: 'Research',
      content: '',
      updatedAt: new Date('2025-01-20T09:30:00'),
      createdAt: new Date('2025-01-12'),
      author: agents[4],
      isFolder: true,
      parentId: null,
    },
    // Document: Competitor Analysis (inside Research folder)
    {
      id: 'd2',
      title: 'Competitor Analysis Report',
      content: `# Competitor Analysis: Beauty Industry Q1 2025

## Executive Summary
Analysis of top 5 competitors in the sustainable beauty space.

## Key Findings

### Pricing
- Average price point: $45-65
- Bloom Beauty opportunity: Premium positioning at $55-75

### Marketing Spend
- Competitors averaging $30-50k/month on paid social
- Heavy TikTok investment trend

### Gaps Identified
1. Lack of transparency in sustainability claims
2. Weak email marketing strategies
3. Limited influencer diversity

## Recommendations
1. Double down on verified sustainability messaging
2. Invest in micro-influencer partnerships
3. Create educational content series`,
      updatedAt: new Date('2025-01-20T09:30:00'),
      createdAt: new Date('2025-01-19'),
      author: agents[4],
      viewers: [humans[1]],
      editors: [agents[4]],
      parentId: 'folder-research',
      isFolder: false,
    },
    // Root document: Weekly Standup Notes (not in any folder)
    {
      id: 'd4',
      title: 'Weekly Standup Notes',
      content: `# Weekly Standup - January 20, 2025

## Progress
- Campaign brief finalized
- Competitor analysis complete
- Creative production started

## Blockers
- Awaiting budget approval for increased ad spend
- Need final product shots from client

## This Week's Focus
- Complete Instagram carousel designs
- Set up tracking pixels
- Begin influencer outreach`,
      updatedAt: new Date('2025-01-20T08:00:00'),
      createdAt: new Date('2025-01-20'),
      author: agents[0],
      viewers: [],
      parentId: null,
      isFolder: false,
    },
  ]

  // Dashboard Stats
  const stats: Stats = {
    agentsOnline: agents.filter(a => a.status !== 'offline').length,
    totalAgents: agents.length,
    tasksCompleted: tasks.filter(t => t.status === 'done').length,
    tasksToday: 3,
    messagesTotal: 1247,
    messagesToday: 89,
    creditsUsed: 847.50,
    creditsRemaining: 2152.50,
  }

  // Activity Feed
  const activities: Activity[] = [
    {
      id: 'act1',
      type: 'task_completed',
      description: 'Scout completed "Competitor analysis"',
      actor: agents[4],
      timestamp: new Date('2025-01-20T09:30:00'),
    },
    {
      id: 'act2',
      type: 'approval_needed',
      description: 'Marcus requests budget increase approval ($3,000)',
      actor: agents[0],
      timestamp: new Date('2025-01-20T09:45:00'),
    },
    {
      id: 'act3',
      type: 'task_started',
      description: 'Dex started "Design Instagram carousel"',
      actor: agents[3],
      timestamp: new Date('2025-01-20T09:00:00'),
    },
    {
      id: 'act4',
      type: 'message',
      description: 'New message in #client-bloom-beauty',
      actor: agents[1],
      timestamp: new Date('2025-01-20T09:15:00'),
    },
    {
      id: 'act5',
      type: 'agent_spawned',
      description: 'Marcus spawned temporary research assistants',
      actor: agents[0],
      timestamp: new Date('2025-01-19T14:00:00'),
    },
  ]

  return {
    users,
    humans,
    agents,
    channels,
    messages,
    tasks,
    documents,
    stats,
    activities,
    pendingApprovals,
  }
}
