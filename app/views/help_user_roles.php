<?php
require_login();
$pageTitle = 'User & Roles Manual';
?>
<div class="card">
    <h2>User & Roles Manual</h2>
    <p>This page explains how offices, departments, posts, positions, staff, users, roles and routes connect inside Yojaka.</p>
    <h3>Concepts</h3>
    <ul>
        <li><strong>Office:</strong> Top-level unit with its own data folder, config, users, hierarchy and routes.</li>
        <li><strong>Department:</strong> Functional area inside an office such as Civil, Electrical or Accounts.</li>
        <li><strong>Post:</strong> Abstract job title (Executive Engineer, Superintending Engineer, Clerk).</li>
        <li><strong>Position (Seat):</strong> Concrete seat within an office and department, tied to a post. One holder at a time.</li>
        <li><strong>Staff (Person):</strong> Real human profile with name, designation and contacts; can move between positions.</li>
        <li><strong>User (Login):</strong> System account mapped to one staff member. A staff record may exist without a login.</li>
        <li><strong>Role:</strong> Permission bundle (admin, officer, clerk) configured in Role Management.</li>
        <li><strong>File Route / Workflow Node:</strong> Route steps reference positions; the current holder is resolved at runtime.</li>
        <li><strong>Actor Type:</strong> Either an authenticated user or an "other" person captured as free text.</li>
        <li><strong>Actual vs Recorded Time:</strong> <em>effective_at</em> is when the action happened; <em>recorded_at</em> is when it was entered.</li>
    </ul>
    <h3>Relationship Chain</h3>
    <p>Office → Department → Post → Position → Staff → User → Role → Routes / File Movements.</p>
    <p>Routes always point to positions. Movement history stores the actor name and timestamps so past records stay accurate even after transfers.</p>
    <h3>Setup Checklist</h3>
    <ol>
        <li>Define departments for the office.</li>
        <li>Create posts (job titles).</li>
        <li>Add positions linked to posts and departments.</li>
        <li>Import or add staff records.</li>
        <li>Assign staff to positions to capture current holder and history.</li>
        <li>Create user accounts linked to staff and assign roles.</li>
        <li>Design file routes that reference positions.</li>
        <li>While moving files, capture actor type, actor name and both actual and recorded time.</li>
    </ol>
    <h3>Notes on History & Actors</h3>
    <ul>
        <li>Position holder history keeps transfers by date. Vacant seats are represented by empty holders.</li>
        <li>File movements default actor_type to <code>user</code> but allow <code>other</code> with a typed name/designation.</li>
        <li>effective_at can be backdated when entering past actions; recorded_at always uses current server time.</li>
    </ul>
</div>
