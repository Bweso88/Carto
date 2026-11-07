// Types des données CMDB envoyées par les contrôleurs Laravel (app/Http/Resources).

export type Option = { value: string; label: string };
export type EnumValue = { value: string; label: string } | null;

export type TechnologyRef = {
    id: number;
    label: string;
    category: EnumValue;
    end_of_support_at: string | null;
    end_of_life_at: string | null;
    obsolete: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
};

export type Server = {
    id: number;
    hostname: string;
    fqdn: string | null;
    kind: EnumValue;
    status: EnumValue;
    criticality: EnumValue;
    primary_ip?: string | null;
    ip_addresses?: {
        address: string;
        version: number;
        label: string | null;
        is_primary: boolean;
    }[];
    environment?: {
        id: number;
        code: string;
        name: string;
        is_production: boolean;
    } | null;
    site?: { id: number; name: string } | null;
    os?: TechnologyRef | null;
    owner_team?: {
        id: number;
        name: string;
        lead_name: string | null;
        email: string | null;
    } | null;
    manufacturer?: { id: number; name: string } | null;
    host?: { id: number; hostname: string } | null;
    guests?: { id: number; hostname: string }[];
    applications_count?: number;
    guests_count?: number;
    model: string | null;
    serial_number: string | null;
    cpu_cores: number | null;
    ram_gb: number | null;
    storage_gb: number | null;
    commissioned_at: string | null;
    warranty_end_at: string | null;
    description: string | null;
    kerberos_id: number | null;
    updated_at: string | null;
};

export type Flow = {
    id: number;
    application: { id: number; code: string; name: string } | null;
    criticality: EnumValue;
    protocol: EnumValue;
    port: number | null;
    is_encrypted: boolean | null;
    is_critical: boolean;
    frequency: string | null;
    description: string | null;
};

export type Deployment = {
    id: number;
    role: EnumValue;
    environment: { id: number; code: string; name: string } | null;
    notes: string | null;
    server: {
        id: number;
        hostname: string;
        status: EnumValue;
        primary_ip: string | null;
        os: TechnologyRef | null;
    };
};

export type Team = {
    id: number;
    name: string;
    lead_name: string | null;
    email: string | null;
    on_call_phone?: string | null;
};

export type Application = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    type: EnumValue;
    lifecycle_status: EnumValue;
    criticality: EnumValue;
    data_classification: EnumValue;
    handles_personal_data: boolean;
    business_domains?: { id: number; name: string }[];
    vendor?: { id: number; name: string } | null;
    business_owner?: Team | null;
    technical_owner?: Team | null;
    technologies?: TechnologyRef[];
    deployments?: Deployment[];
    outgoing_flows?: Flow[];
    incoming_flows?: Flow[];
    servers_count?: number;
    dependencies_count?: number;
    dependents_count?: number;
    user_base: string | null;
    user_count: number | null;
    sla_target: number | null;
    rto_minutes: number | null;
    rpo_minutes: number | null;
    documentation_url: string | null;
    kerberos_id: number | null;
    updated_at: string | null;
};

export type EnumOptions = {
    criticalities: Option[];
    serverKinds: Option[];
    serverStatuses: Option[];
    applicationTypes: Option[];
    lifecycleStatuses: Option[];
    dataClassifications: Option[];
    deploymentRoles: Option[];
    flowProtocols: Option[];
    technologyCategories: Option[];
};

// ---------------------------------------------------------------- Topologie

export type ImpactState = 'down' | 'degraded' | null | undefined;

export type AppNodeData = {
    id: number;
    label: string;
    code: string;
    kind: string;
    kindLabel: string;
    criticality: string | null;
    criticalityLabel: string | null;
    lifecycle: string;
    obsolete: boolean;
    personalData: boolean;
    impact?: ImpactState;
    root?: boolean;
};

export type ServerNodeData = {
    id: number;
    label: string;
    ip: string | null;
    kind: string;
    kindLabel: string;
    status: string;
    criticality: string | null;
    os: string | null;
    obsolete: boolean;
    impact?: ImpactState;
};

export type GraphNode =
    | { id: string; type: 'application'; data: AppNodeData }
    | { id: string; type: 'server'; data: ServerNodeData };

export type GraphEdge = {
    id: string;
    source: string;
    target: string;
    kind: 'flow' | 'deployment' | 'hosting';
    data: {
        id?: number;
        protocol?: string;
        port?: number | null;
        encrypted?: boolean | null;
        critical?: boolean;
        role?: string;
    };
};

export type Graph = {
    nodes: GraphNode[];
    edges: GraphEdge[];
    truncated: boolean;
};

export type ImpactReport = {
    failed_servers: { id: number; hostname: string }[];
    applications: {
        id: number;
        code: string;
        name: string;
        criticality: string | null;
        criticality_label: string | null;
        status: 'down' | 'degraded';
        depth: number;
        via: { id: number; code: string; name: string } | null;
        is_root: boolean;
        user_count: number | null;
    }[];
    teams: {
        id: number;
        name: string;
        lead_name: string | null;
        email: string | null;
        on_call_phone: string | null;
        down: number;
        applications: { code: string; name: string; status: string }[];
    }[];
    summary: {
        down: number;
        degraded: number;
        critical_down: number;
        cascade_levels: number;
        impacted_users: number;
        score: number;
    };
};
